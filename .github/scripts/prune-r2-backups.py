#!/usr/bin/env python3
"""
Retention policy for Cloudflare R2 MySQL backups.

Rules:
- Keep daily backups for 30 days.
- Keep monthly backups (created on the 1st of the month) for 12 months.
- Delete everything older.
"""

import os
import re
import boto3
from datetime import datetime, timezone
from dateutil.relativedelta import relativedelta

BUCKET = os.environ['R2_BUCKET']
ENDPOINT = os.environ['R2_ENDPOINT']
ACCESS_KEY = os.environ['R2_ACCESS_KEY_ID']
SECRET_KEY = os.environ['R2_SECRET_ACCESS_KEY']

FILENAME_RE = re.compile(r'^bookings-backup-(\d{8})-(\d{6})\.sql\.gz$')


def main():
    s3 = boto3.client(
        's3',
        endpoint_url=ENDPOINT,
        aws_access_key_id=ACCESS_KEY,
        aws_secret_access_key=SECRET_KEY,
        region_name='auto',
    )

    objects = []
    paginator = s3.get_paginator('list_objects_v2')
    for page in paginator.paginate(Bucket=BUCKET):
        for obj in page.get('Contents', []):
            key = obj['Key']
            match = FILENAME_RE.match(key)
            if not match:
                continue

            timestamp = datetime.strptime(match.group(1) + match.group(2), '%Y%m%d%H%M%S')
            timestamp = timestamp.replace(tzinfo=timezone.utc)
            objects.append({
                'Key': key,
                'timestamp': timestamp,
                'is_monthly': timestamp.day == 1,
            })

    if not objects:
        print('No backup files found matching the expected pattern.')
        return

    now = datetime.now(timezone.utc)
    cutoff_daily = now - relativedelta(days=30)
    cutoff_monthly = now - relativedelta(months=12)
    delete_keys = []

    for obj in objects:
        ts = obj['timestamp']

        # Keep monthly backups (1st of month) for 12 months
        if obj['is_monthly'] and ts >= cutoff_monthly:
            print(f'KEEP (monthly): {obj["Key"]}')
            continue

        # Keep daily backups for 30 days
        if ts >= cutoff_daily:
            print(f'KEEP (daily): {obj["Key"]}')
            continue

        print(f'DELETE: {obj["Key"]}')
        delete_keys.append({'Key': obj['Key']})

    if not delete_keys:
        print('No backups to delete.')
        return

    # R2/S3 delete_objects supports up to 1000 keys per call
    batch_size = 1000
    total_deleted = 0
    for i in range(0, len(delete_keys), batch_size):
        batch = delete_keys[i:i + batch_size]
        response = s3.delete_objects(Bucket=BUCKET, Delete={'Objects': batch})
        deleted = response.get('Deleted', [])
        total_deleted += len(deleted)

    print(f'Deleted {total_deleted} old backup(s).')


if __name__ == '__main__':
    main()
