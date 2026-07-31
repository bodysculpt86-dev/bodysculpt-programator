<?php
/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Easy!Appointments Configuration File
 *
 * Set your installation BASE_URL * without the trailing slash * and the database
 * credentials in order to connect to the database. You can enable the DEBUG_MODE
 * while developing the application.
 *
 * Set the default language by changing the LANGUAGE constant. For a full list of
 * available languages look at the /application/config/config.php file.
 *
 * IMPORTANT:
 * If you are updating from version 1.0 you will have to create a new "config.php"
 * file because the old "configuration.php" is not used anymore.
 */
class Config
{
    // ------------------------------------------------------------------------
    // GENERAL SETTINGS
    // ------------------------------------------------------------------------

    const BASE_URL = 'http://localhost';
    const LANGUAGE = 'english';
    const DEBUG_MODE = false;

    // ------------------------------------------------------------------------
    // DATABASE SETTINGS
    // ------------------------------------------------------------------------

    const DB_HOST = 'mysql';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = 'user';
    const DB_PASSWORD = 'password';

    // ------------------------------------------------------------------------
    // GOOGLE CALENDAR SYNC (Optional - can also be configured via UI)
    // ------------------------------------------------------------------------
    // These settings are optional and can be configured through the admin UI
    // at Settings > Integrations > Google Calendar. If configured here, they
    // will be used as fallback values.
    //
    // const GOOGLE_SYNC_FEATURE = false;
    // const GOOGLE_CLIENT_ID = '';
    // const GOOGLE_CLIENT_SECRET = '';

    // ------------------------------------------------------------------------
    // SMSO.RO SMS NOTIFICATIONS (Optional)
    // ------------------------------------------------------------------------
    // Set SMSO_API_KEY to your SMSO.ro API key to enable SMS notifications.
    // Set SMSO_SENDER_ID to the numeric sender ID from your SMSO dashboard.
    // Use SMSO_API_KEY=LOG_ONLY to log messages without sending real SMS.
    //
    // const SMSO_API_KEY = '';
    // const SMSO_SENDER_ID = '';

    // ------------------------------------------------------------------------
    // FLAXXA WHATSAPP NOTIFICATIONS (Optional)
    // ------------------------------------------------------------------------
    // Set FLAXXA_API_TOKEN to your Flaxxa WAPI token to enable WhatsApp notifications.
    // Set FLAXXA_CONFIRMATION_TEMPLATE and FLAXXA_REMINDER_TEMPLATE to the approved template names.
    // Set FLAXXA_TEMPLATE_LANGUAGE to the template language code (default: ro).
    // Use FLAXXA_API_TOKEN=LOG_ONLY to log messages without sending real WhatsApp messages.
    //
    // const FLAXXA_API_TOKEN = '';
    // const FLAXXA_CONFIRMATION_TEMPLATE = '';
    // const FLAXXA_REMINDER_TEMPLATE = '';
    // const FLAXXA_TEMPLATE_LANGUAGE = 'ro';

    // ------------------------------------------------------------------------
    // EVOLUTION API WHATSAPP INTEGRATION (Optional)
    // ------------------------------------------------------------------------
    // In production these values are read from environment variables
    // (EVOLUTION_API_URL, EVOLUTION_API_KEY, EVOLUTION_INSTANCE) set in Railway.
    // The constants below are an optional local fallback for development only.
    //
    // const EVOLUTION_API_URL = '';
    // const EVOLUTION_API_KEY = '';
    // const EVOLUTION_INSTANCE = '';

    // ------------------------------------------------------------------------
    // STRIPE DEPOSIT PAYMENTS (Optional)
    // ------------------------------------------------------------------------
    // In production these values are read from environment variables
    // (STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, STRIPE_DEPOSIT_AMOUNT,
    // STRIPE_PAYMENT_TEMPLATE_NAME) set in Railway. The constants below are an
    // optional local fallback for development only. Never commit real keys.
    //
    // Set STRIPE_DEPOSIT_AMOUNT to the fixed deposit amount (e.g. '100.00').
    // Set STRIPE_PAYMENT_TEMPLATE_NAME to the approved WhatsApp template used to
    // deliver the payment link ('payment_link_deposit' once approved by Meta).
    // Leave STRIPE_SECRET_KEY empty to disable the deposit feature.
    //
    // const STRIPE_SECRET_KEY = '';
    // const STRIPE_WEBHOOK_SECRET = '';
    // const STRIPE_DEPOSIT_AMOUNT = '100.00';
    // const STRIPE_PAYMENT_TEMPLATE_NAME = 'payment_link_deposit';
    //
    // CLIENT_CANCEL_TEMPLATE_NAME: approved WhatsApp template sent to the
    // customer when an appointment is auto-cancelled for an unpaid deposit
    // (see Console::process_unpaid_deposits). Variables: {{header_1}} = customer
    // name, {{body_1}} = service, {{body_2}} = appointment date/time.
    //
    // const CLIENT_CANCEL_TEMPLATE_NAME = 'avans_neplatit';
}
