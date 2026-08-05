# Generates assets/js/data/ro_locations.js (42 judete + 103 municipii).
# Sources: ro.wikipedia "Municipiile Romaniei" (SIRUTA-based) + en.wikipedia city->county list.
import re, json, unicodedata, urllib.request

def fetch(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    return urllib.request.urlopen(req, timeout=60).read().decode('utf-8')

def clean(name):
    name = re.sub(r',\s*România$', '', name)
    name = re.sub(r'\s*\(oraș\)$', '', name)
    return unicodedata.normalize('NFC', name).strip()

html = fetch('https://ro.wikipedia.org/wiki/Lista_municipiilor_din_Rom%C3%A2nia')
tables = re.findall(r'<table[^>]*>(.*?)</table>', html, re.S)

counties = []
for r in re.findall(r'<tr[^>]*>(.*?)</tr>', tables[2], re.S)[1:]:
    cells = re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.S)
    if cells:
        j = unicodedata.normalize('NFC', re.sub(r'<[^>]+>', '', cells[0]).strip())
        if j:
            counties.append(j)

muns, seen = [], set()
for l in re.findall(r'<a[^>]*title="([^"]+)"[^>]*>', tables[3]):
    l = clean(l)
    if re.match(r'(Format|Vizualizează|Discu|Modifică|Lista)', l) or l in seen:
        continue
    seen.add(l)
    muns.append(l)

html2 = fetch('https://en.wikipedia.org/wiki/List_of_cities_and_towns_in_Romania')
t = re.search(r'<table class="sortable wikitable".*?</table>', html2, re.S).group(0)
city2county = {}
for r in re.findall(r'<tr[^>]*>(.*?)</tr>', t, re.S)[1:]:
    cells = re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.S)
    if len(cells) < 2:
        continue
    city = unicodedata.normalize('NFC', re.sub(r'<[^>]+>', '', cells[0]).strip())
    county = unicodedata.normalize('NFC', re.sub(r'<[^>]+>', '', cells[1]).strip())
    if city:
        city2county[city] = county

mun_map = {c: [] for c in counties}
for m in muns:
    if m == 'București':
        continue
    c = city2county.get(m)
    if c is None and m == 'Roșiori de Vede':
        c = city2county.get('Roșiorii de Vede')
    if c in mun_map:
        mun_map[c].append(m)
    else:
        raise SystemExit('UNMAPPED: ' + m)

mun_map['București'] = [f'Sector {i} București' for i in range(1, 7)]
counties = sorted(counties + ['București'])
for c in mun_map:
    mun_map[c].sort()

data = {'counties': counties, 'municipalities': {c: mun_map[c] for c in counties}}
total = sum(len(v) for v in mun_map.values())
assert len(counties) == 42 and total - 6 + 1 == 103, (len(counties), total)

header = '''/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Romanian administrative units reference dataset (judete + municipii).
 *
 * Static reference data used to provide autocomplete SUGGESTIONS on the
 * county (judet) and city (oras) text inputs across the app. Suggestions
 * only - all fields remain free-text, any typed value is saved as-is.
 *
 * Sources (canonical, SIRUTA-based):
 *  - Counties + municipalities list: ro.wikipedia.org "Municipiile Romaniei"
 *    (mirrors the official SIRUTA register / Legea 351/2001, 103 municipii)
 *  - City-to-county mapping: en.wikipedia.org "List of cities and towns in Romania"
 *
 * Municipiul Bucuresti is a special county-level unit whose "cities" are its
 * 6 administrative sectors, formatted "Sector N Bucuresti".
 */
window.App = window.App || {};
App.Data = App.Data || {};

App.Data.RoLocations = '''

with open('assets/js/data/ro_locations.js', 'w', encoding='utf-8') as f:
    f.write(header + json.dumps(data, ensure_ascii=False, indent=4) + ';\n')

print('OK counties:', len(counties), '| municipii+sectoare entries:', total)
