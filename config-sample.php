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
    // Set EVOLUTION_API_URL to your Evolution API public URL.
    // Set EVOLUTION_API_KEY to your Evolution API authentication key.
    // Set EVOLUTION_INSTANCE to the instance name shown in Evolution Manager.
    //
    // const EVOLUTION_API_URL = '';
    // const EVOLUTION_API_KEY = '';
    // const EVOLUTION_INSTANCE = '';
}
