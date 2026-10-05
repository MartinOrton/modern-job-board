<?php
/**
 * Plugin Name: Modern Job Board
 * Plugin URI: https://martinorton.com/modern-job-board
 * Description: A freemium job board plugin for WordPress (pre-stable beta — not 1.0).
 * Version: 0.9.0-beta.149
 * Author: Martin Orton
 * Author URI: https://www.martinorton.com
 * License: Proprietary
 * License URI: https://martinorton.com/modern-job-board
 * Text Domain: modern-job-board
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('MJB_VERSION', '0.9.0-beta.149');
define('MJB_PATH', plugin_dir_path(__FILE__));
define('MJB_URL', plugin_dir_url(__FILE__));

require_once MJB_PATH . 'includes/class-mjb-license.php';
require_once MJB_PATH . 'includes/class-mjb-license-commerce.php';
require_once MJB_PATH . 'includes/class-mjb-license-remote.php';
require_once MJB_PATH . 'includes/class-mjb-private-uploads.php';
require_once MJB_PATH . 'includes/class-mjb-resumes.php';
require_once MJB_PATH . 'includes/class-mjb-document-label.php';
require_once MJB_PATH . 'includes/class-mjb-account-status.php';
require_once MJB_PATH . 'includes/class-mjb-admin-bar.php';
require_once MJB_PATH . 'includes/class-mjb-saved-jobs.php';
require_once MJB_PATH . 'includes/class-mjb-activator.php';
require_once MJB_PATH . 'includes/class-mjb-notices.php';
require_once MJB_PATH . 'includes/class-mjb-page-resolver.php';
require_once MJB_PATH . 'includes/class-mjb-job-routes.php';
require_once MJB_PATH . 'includes/class-mjb-job-permalinks.php';
require_once MJB_PATH . 'includes/class-mjb-application-guard.php';
require_once MJB_PATH . 'includes/class-mjb-recaptcha.php';
require_once MJB_PATH . 'includes/class-mjb-job-importer.php';
require_once MJB_PATH . 'includes/class-mjb-xml-importer.php';
require_once MJB_PATH . 'includes/class-mjb-import-scheduler.php';
require_once MJB_PATH . 'includes/class-mjb-page-wizard.php';
require_once MJB_PATH . 'includes/class-mjb-application-status.php';
require_once MJB_PATH . 'includes/class-mjb-rest-api-v2.php';
require_once MJB_PATH . 'includes/class-mjb-data-grid.php';
require_once MJB_PATH . 'includes/class-mjb-blocks.php';
require_once MJB_PATH . 'includes/class-mjb-analytics.php';
require_once MJB_PATH . 'includes/class-mjb-webhook-queue.php';
require_once MJB_PATH . 'includes/class-mjb-webhooks.php';
// Product backlog feature modules (#6–#42).
require_once MJB_PATH . 'includes/class-mjb-legacy-redirects.php';
require_once MJB_PATH . 'includes/class-mjb-private-board.php';
require_once MJB_PATH . 'includes/class-mjb-job-alerts.php';
require_once MJB_PATH . 'includes/class-mjb-talent-pool.php';
require_once MJB_PATH . 'includes/class-mjb-collaborators.php';
require_once MJB_PATH . 'includes/class-mjb-embed.php';
require_once MJB_PATH . 'includes/class-mjb-brand.php';
require_once MJB_PATH . 'includes/class-mjb-string-overrides.php';
require_once MJB_PATH . 'includes/class-mjb-auto-approve.php';
require_once MJB_PATH . 'includes/class-mjb-company-preview.php';
require_once MJB_PATH . 'includes/class-mjb-messaging.php';
require_once MJB_PATH . 'includes/class-mjb-analytics-export.php';
require_once MJB_PATH . 'includes/class-mjb-api-keys.php';
require_once MJB_PATH . 'includes/class-mjb-pwa.php';
require_once MJB_PATH . 'includes/class-mjb-filter-settings.php';
require_once MJB_PATH . 'includes/class-mjb-packages.php';
require_once MJB_PATH . 'includes/class-mjb-partner-import.php';
require_once MJB_PATH . 'includes/class-mjb-seo-landings.php';
require_once MJB_PATH . 'includes/class-mjb-media-listings.php';
require_once MJB_PATH . 'includes/class-mjb-sms.php';
require_once MJB_PATH . 'includes/class-mjb-i18n-board.php';
require_once MJB_PATH . 'includes/class-mjb-blog-package.php';
// WPJB parity track (A1–A8 core modules).
require_once MJB_PATH . 'includes/class-mjb-google-jobs.php';
require_once MJB_PATH . 'includes/class-mjb-job-ops.php';
require_once MJB_PATH . 'includes/class-mjb-resume-privacy.php';
require_once MJB_PATH . 'includes/class-mjb-jobs-map.php';
require_once MJB_PATH . 'includes/class-mjb-promotions.php';
require_once MJB_PATH . 'includes/class-mjb-ingestion.php';
require_once MJB_PATH . 'includes/class-mjb-memberships.php';
require_once MJB_PATH . 'includes/class-mjb-commerce-admin.php';
require_once MJB_PATH . 'includes/class-mjb-email-templates.php';
require_once MJB_PATH . 'includes/class-mjb-mailchimp.php';
require_once MJB_PATH . 'includes/class-mjb-board-polish.php';

register_activation_hook(__FILE__, array('MJB_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('MJB_Activator', 'deactivate'));

// Include core classes.
require_once MJB_PATH . 'includes/class-mjb-cpt.php';
require_once MJB_PATH . 'includes/class-mjb-icons.php';
require_once MJB_PATH . 'includes/class-mjb-location.php';
require_once MJB_PATH . 'includes/class-mjb-shortcodes.php';
require_once MJB_PATH . 'includes/class-mjb-admin-tabs.php';
require_once MJB_PATH . 'includes/class-mjb-admin-dashboard.php';
require_once MJB_PATH . 'includes/class-mjb-admin-jobs.php';
require_once MJB_PATH . 'includes/class-mjb-admin-companies.php';
require_once MJB_PATH . 'includes/class-mjb-admin-applications.php';
require_once MJB_PATH . 'includes/class-mjb-admin-resumes.php';
require_once MJB_PATH . 'includes/class-mjb-admin.php';
require_once MJB_PATH . 'includes/class-mjb-template-loader.php';
require_once MJB_PATH . 'includes/class-mjb-applications.php';
require_once MJB_PATH . 'includes/class-mjb-pretty-urls.php';
require_once MJB_PATH . 'includes/class-mjb-search.php';
require_once MJB_PATH . 'includes/class-mjb-dashboard.php';
require_once MJB_PATH . 'includes/class-mjb-emails.php';

/**
 * Main Plugin Class
 */
class Modern_Job_Board
{

    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        MJB_License::init();
        MJB_License_Commerce::init();
        MJB_License_Remote::init();
        MJB_Page_Resolver::init();
        MJB_Job_Routes::init();
        MJB_Job_Permalinks::init();
        MJB_Pretty_Urls::init();
        MJB_Legacy_Redirects::init();
        MJB_Page_Wizard::init();
        MJB_Analytics::init();
        MJB_Private_Board::init();
        MJB_Job_Alerts::init();
        MJB_Talent_Pool::init();
        MJB_Collaborators::init();
        MJB_Embed::init();
        MJB_Brand::init();
        MJB_String_Overrides::init();
        MJB_Auto_Approve::init();
        MJB_Company_Preview::init();
        MJB_Messaging::init();
        MJB_Analytics_Export::init();
        MJB_Api_Keys::init();
        MJB_Pwa::init();
        MJB_Filter_Settings::init();
        MJB_Packages::init();
        MJB_Partner_Import::init();
        MJB_Seo_Landings::init();
        MJB_Media_Listings::init();
        MJB_Sms::init();
        MJB_I18n_Board::init();
        MJB_Blog_Package::init();
        MJB_Google_Jobs::init();
        MJB_Job_Ops::init();
        MJB_Resume_Privacy::init();
        MJB_Document_Label::init();
        MJB_Jobs_Map::init();
        MJB_Promotions::init();
        MJB_Ingestion::init();
        MJB_Memberships::init();
        MJB_Commerce_Admin::init();
        MJB_Email_Templates::init();
        MJB_Mailchimp::init();
        MJB_Application_Guard::init();
        MJB_Board_Polish::init();
        if (MJB_License::can('webhooks')) {
            MJB_Webhooks::init();
            MJB_Webhook_Queue::init();
        }
        if (MJB_License::can('tools')) {
            MJB_Import_Scheduler::init();
            MJB_Job_Importer::init();
        }
        MJB_Blocks::init();
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));

        MJB_Private_Uploads::init();
        MJB_Account_Status::init();
        MJB_Admin_Bar::init();
        MJB_Saved_Jobs::init();

        $resumes = new MJB_Resumes();
        $resumes->init();

        // Initialize CPTs
        $cpt = new MJB_CPT();
        $cpt->init();

        // Initialize Shortcodes
        $shortcodes = new MJB_Shortcodes();
        $shortcodes->init();

        // Initialize Admin
        if (is_admin()) {
            $admin = new MJB_Admin();
            $admin->init();
        }

        // Initialize Template Loader
        $template_loader = new MJB_Template_Loader();
        $template_loader->init();

        // Initialize Applications
        $applications = new MJB_Applications();
        $applications->init();

        // Initialize Search
        $search = new MJB_Search();
        $search->init();

        // Initialize Dashboard
        $dashboard = new MJB_Dashboard();
        $dashboard->init();

        // Initialize Emails
        global $mjb_emails;
        $mjb_emails = new MJB_Emails();
        $mjb_emails->init();

        // Initialize Cron
        require_once MJB_PATH . 'includes/class-mjb-cron.php';
        $cron = new MJB_Cron();
        $cron->init();

        // Initialize Employer Registration
        require_once MJB_PATH . 'includes/class-mjb-employer-registration.php';
        $registration = new MJB_Employer_Registration();
        $registration->init();

        // Initialize Candidate Registration
        require_once MJB_PATH . 'includes/class-mjb-candidate-registration.php';
        $candidate_registration = new MJB_Candidate_Registration();
        $candidate_registration->init();

        // Initialize frontend login forms
        require_once MJB_PATH . 'includes/class-mjb-login.php';
        $mjb_login = new MJB_Login();
        $mjb_login->init();

        // Initialize Candidate Dashboard
        require_once MJB_PATH . 'includes/class-mjb-candidate-account.php';
        require_once MJB_PATH . 'includes/class-mjb-candidate-dashboard.php';
        $candidate_dashboard = new MJB_Candidate_Dashboard();
        $candidate_dashboard->init();

        // Pro: WooCommerce monetization
        if (MJB_License::can('woocommerce') && class_exists('WooCommerce')) {
            require_once MJB_PATH . 'includes/class-mjb-woocommerce.php';
            $mjb_woocommerce = new MJB_WooCommerce();
            $mjb_woocommerce->init();
        }

        // Pro: Custom Fields
        if (MJB_License::can('custom_fields')) {
            require_once MJB_PATH . 'includes/class-mjb-custom-fields.php';
            global $mjb_custom_fields;
            $mjb_custom_fields = new MJB_Custom_Fields();
            $mjb_custom_fields->init();
        }

        // Pro: Tools (CSV Import/Export)
        if (MJB_License::can('tools')) {
            require_once MJB_PATH . 'includes/class-mjb-tools.php';
            global $mjb_tools;
            $mjb_tools = new MJB_Tools();
            $mjb_tools->init();
        }

        // Business: Feeds & REST API
        if (MJB_License::can('xml_feed')) {
            require_once MJB_PATH . 'includes/class-mjb-feeds.php';
            $mjb_feeds = new MJB_Feeds();
            $mjb_feeds->init();
        }

        if (MJB_License::can('rest_api')) {
            require_once MJB_PATH . 'includes/class-mjb-rest-api.php';
            $mjb_api = new MJB_REST_API();
            $mjb_api->init();

            $mjb_api_v2 = new MJB_REST_API_V2();
            $mjb_api_v2->init();
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('modern-job-board', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Whether frontend assets should load on the current request.
     *
     * @return bool
     */
    private function should_load_assets()
    {
        if (is_singular(array('job_listing', 'company')) || is_post_type_archive(array('job_listing', 'company')) || is_tax(array('job_type', 'job_category', 'job_location'))) {
            return true;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return false;
        }

        $shortcodes = array(
            'mjb_jobs',
            'mjb_job_form',
            'mjb_dashboard',
            'mjb_employer_registration',
            'mjb_candidate_registration',
            'mjb_candidate_dashboard',
            'mjb_candidate_login',
            'mjb_employer_login',
        );

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether reCAPTCHA should load on the current request.
     *
     * @return bool
     */
    private function should_enqueue_recaptcha()
    {
        if (!MJB_Recaptcha::is_enabled()) {
            return false;
        }

        if (is_singular('job_listing')) {
            return true;
        }

        return $this->page_has_registration_shortcode();
    }

    /**
     * Multi-step registration wizard assets.
     *
     * @return bool
     */
    private function should_enqueue_registration_wizard()
    {
        return $this->page_has_registration_shortcode();
    }

    /**
     * Country-code phone field (registration and candidate dashboard).
     *
     * @return bool
     */
    private function should_enqueue_phone_field()
    {
        return $this->should_enqueue_registration_wizard() || $this->page_has_shortcode('mjb_candidate_dashboard');
    }

    /**
     * @param string $tag
     * @return bool
     */
    private function page_has_shortcode($tag)
    {
        global $post;
        if (!$post instanceof WP_Post) {
            return false;
        }

        return has_shortcode($post->post_content, $tag);
    }

    /**
     * @return bool
     */
    private function page_has_registration_shortcode()
    {
        global $post;
        if (!$post instanceof WP_Post) {
            return false;
        }

        $registration_shortcodes = array(
            'mjb_candidate_registration',
            'mjb_employer_registration',
        );

        foreach ($registration_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts()
    {
        if (!$this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'mjb-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
            array(),
            null
        );
        wp_enqueue_style('mjb-shared', MJB_URL . 'assets/css/mjb-shared.css', array(), MJB_VERSION);
        wp_enqueue_style('mjb-style', MJB_URL . 'assets/css/mjb-style.css', array('mjb-shared', 'mjb-fonts'), MJB_VERSION);
        wp_enqueue_style('mjb-board-polish', MJB_URL . 'assets/css/mjb-board-polish.css', array('mjb-style'), MJB_VERSION);

        // Admin-style performance charts + AJAX tabs on the recruiter dashboard.
        global $post;
        if ($post instanceof WP_Post && has_shortcode($post->post_content, 'mjb_dashboard')) {
            wp_enqueue_style(
                'mjb-charts',
                MJB_URL . 'assets/css/mjb-charts.css',
                array('mjb-style'),
                MJB_VERSION
            );
            wp_enqueue_script(
                'mjb-recruiter-dashboard',
                MJB_URL . 'assets/js/mjb-recruiter-dashboard.js',
                array('jquery'),
                MJB_VERSION,
                true
            );
            $tab_urls = array();
            if (class_exists('MJB_Dashboard')) {
                foreach (array_keys(MJB_Dashboard::get_tabs()) as $tab_id) {
                    $tab_urls[$tab_id] = MJB_Dashboard::get_tab_url($tab_id);
                }
            }
            $skeletons = array();
            if (class_exists('MJB_Dashboard')) {
                foreach (array_keys(MJB_Dashboard::get_tabs()) as $tab_id) {
                    $skeletons[$tab_id] = MJB_Dashboard::get_tab_skeleton_html($tab_id);
                }
            }
            wp_localize_script('mjb-recruiter-dashboard', 'mjb_recruiter_dashboard', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mjb_recruiter_dashboard'),
                'default_tab' => 'overview',
                'tabs' => $tab_urls,
                'skeletons' => $skeletons,
                'i18n' => array(
                    'loading' => __('Loading dashboard', 'modern-job-board'),
                ),
            ));
        }

        if ($this->should_enqueue_recaptcha()) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }

        wp_enqueue_script('mjb-ajax-search', MJB_URL . 'assets/js/mjb-ajax-search.js', array('jquery'), MJB_VERSION, true);
        wp_localize_script('mjb-ajax-search', 'mjb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mjb_search_nonce'),
            'jobs_search_base' => trailingslashit(MJB_Job_Routes::build_url()),
            'jobs_api_search_base' => trailingslashit(MJB_Job_Routes::build_url(array(), array('rest' => true))),
            'i18n' => array(
                'noResults' => __('No matches', 'modern-job-board'),
                'loading' => __('Loading jobs', 'modern-job-board'),
            ),
        ));

        wp_enqueue_script(
            'mjb-form-validation',
            MJB_URL . 'assets/js/mjb-form-validation.js',
            array(),
            MJB_VERSION,
            true
        );
        wp_localize_script('mjb-form-validation', 'mjbFormValidation', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'saving' => __('Saving…', 'modern-job-board'),
            'saveFailed' => __('Changes could not be saved. Try again.', 'modern-job-board'),
            'updateJob' => __('Update Job', 'modern-job-board'),
            'applied' => __('Applied', 'modern-job-board'),
            'savedJob' => __('Saved', 'modern-job-board'),
            'saveJob' => __('Save', 'modern-job-board'),
            'required' => __('This field is required.', 'modern-job-board'),
            'email' => __('Please enter a valid email address.', 'modern-job-board'),
            'url' => __('Please enter a valid URL.', 'modern-job-board'),
            'number' => __('Please enter a valid number.', 'modern-job-board'),
            /* translators: %d: minimum character count */
            'minlength' => __('Please enter at least %d characters.', 'modern-job-board'),
            /* translators: %d: maximum character count */
            'maxlength' => __('Please enter no more than %d characters.', 'modern-job-board'),
            'pattern' => __('Please match the requested format.', 'modern-job-board'),
            'passwordMatch' => __('Passwords do not match.', 'modern-job-board'),
            'requiredLegend' => __('Required fields are marked with *', 'modern-job-board'),
            'requiredLegendShort' => __('Required fields', 'modern-job-board'),
        ));

        if ($this->should_enqueue_phone_field()) {
            // Full metadata build — accurate AsYouType for every country.
            wp_enqueue_script(
                'libphonenumber',
                MJB_URL . 'assets/js/vendor/libphonenumber-max.js',
                array(),
                '1.11.18',
                true
            );

            wp_enqueue_script(
                'mjb-phone-field',
                MJB_URL . 'assets/js/mjb-phone-field.js',
                array('libphonenumber', 'mjb-ajax-search'),
                MJB_VERSION,
                true
            );
        }

        if ($this->should_enqueue_registration_wizard()) {
            // City autocomplete reuses Filter Jobs AC (jQuery + mjb_ajax → geocity API).
            // mjb-ajax-search is already enqueued above when assets load.

            wp_enqueue_script(
                'mjb-registration-wizard',
                MJB_URL . 'assets/js/mjb-registration-wizard.js',
                array('mjb-phone-field', 'mjb-ajax-search'),
                MJB_VERSION,
                true
            );
            wp_localize_script('mjb-registration-wizard', 'mjbRegistrationWizard', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mjb_registration_wizard'),
                'checkEmailAction' => 'mjb_check_registration_email',
                'i18n' => array(
                    'required' => __('This field is required.', 'modern-job-board'),
                    'email' => __('Please enter a valid email address.', 'modern-job-board'),
                    'passwordMatch' => __('Passwords do not match.', 'modern-job-board'),
                    /* translators: %d: minimum character count */
                    'minlength' => __('Please enter at least %d characters.', 'modern-job-board'),
                    'emailExists' => __('That email address is already registered.', 'modern-job-board'),
                    'submitting' => __('Creating your account…', 'modern-job-board'),
                    'failed' => __('Registration failed. Please try again.', 'modern-job-board'),
                    'network' => __('Network error. Please try again.', 'modern-job-board'),
                ),
            ));
        }

        if ($post instanceof WP_Post && has_shortcode($post->post_content, 'mjb_candidate_dashboard')) {
            wp_enqueue_style(
                'mjb-candidate-dashboard',
                MJB_URL . 'assets/css/mjb-candidate-dashboard.css',
                array('mjb-style'),
                MJB_VERSION
            );
            wp_enqueue_script(
                'mjb-candidate-dashboard',
                MJB_URL . 'assets/js/mjb-candidate-dashboard.js',
                array(),
                MJB_VERSION,
                true
            );
            wp_localize_script('mjb-candidate-dashboard', 'mjbCandidateDashboard', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mjb_candidate_layer'),
                'skeletons' => array(
                    'home' => MJB_Candidate_Dashboard::skeleton_html('home'),
                    'account' => MJB_Candidate_Dashboard::skeleton_html('account'),
                ),
                'spinner' => MJB_Candidate_Dashboard::spinner_html(),
                'i18n' => array(
                    'stepToComplete' => __('1 step to a complete profile', 'modern-job-board'),
                    /* translators: %d: number of incomplete profile items */
                    'stepsToComplete' => __('%d steps to a complete profile', 'modern-job-board'),
                    'stepLeft' => __('1 step', 'modern-job-board'),
                    /* translators: %d: number of incomplete profile items */
                    'stepsLeft' => __('%d steps', 'modern-job-board'),
                    'left' => __('left', 'modern-job-board'),
                    'finish' => __('Finish now', 'modern-job-board'),
                    'complete' => __('Complete — recruiters see your full profile', 'modern-job-board'),
                    /* translators: 1: completed count, 2: total count */
                    'of' => __('%1$d of %2$d', 'modern-job-board'),
                    /* translators: 1: completed count, 2: total count */
                    'ofLabel' => __('%1$d of %2$d profile items complete', 'modern-job-board'),
                    'done' => __('done', 'modern-job-board'),
                    'needed' => __('still needed', 'modern-job-board'),
                    'unsaved' => __('You have unsaved changes', 'modern-job-board'),
                    'saved' => __('All changes saved', 'modern-job-board'),
                    'saving' => __('Saving…', 'modern-job-board'),
                    'saveChanges' => __('Save changes', 'modern-job-board'),
                    'saveFailed' => __('Changes could not be saved. Try again.', 'modern-job-board'),
                    'discarded' => __('Changes discarded', 'modern-job-board'),
                    'visible' => __('Visible', 'modern-job-board'),
                    'hidden' => __('Hidden', 'modern-job-board'),
                    'visibleSub' => __('Recruiters can find you in the talent pool', 'modern-job-board'),
                    'hiddenSub' => __('You are not listed in the talent pool', 'modern-job-board'),
                    'change' => __('Change', 'modern-job-board'),
                    'add' => __('Add', 'modern-job-board'),
                    'weak' => __('Weak', 'modern-job-board'),
                    'fair' => __('Fair', 'modern-job-board'),
                    'good' => __('Good', 'modern-job-board'),
                    'strong' => __('Strong', 'modern-job-board'),
                    'showPassword' => __('Show password', 'modern-job-board'),
                    'hidePassword' => __('Hide password', 'modern-job-board'),
                    'alertOff' => __('You won’t get job alerts by email. You can still browse matches on the site.', 'modern-job-board'),
                    'alertDaily' => __('New jobs that match your saved searches, each day.', 'modern-job-board'),
                    'alertWeekly' => __('New jobs that match your saved searches, once a week.', 'modern-job-board'),
                    'loading' => __('Loading…', 'modern-job-board'),
                    'uploading' => __('Uploading…', 'modern-job-board'),
                    'photoReady' => __('Ready to save', 'modern-job-board'),
                    'photoInvalid' => __('Please choose a JPG, PNG, or WebP image.', 'modern-job-board'),
                    'photoLarge' => __('That photo is larger than 2 MB.', 'modern-job-board'),
                    'passwordRequired' => __('Enter your current password.', 'modern-job-board'),
                    'passwordShort' => __('Use 12 or more characters for your new password.', 'modern-job-board'),
                    'passwordUpdating' => __('Updating…', 'modern-job-board'),
                    'passwordUpdate' => __('Update password', 'modern-job-board'),
                    'passwordUpdated' => __('Password updated. You are still signed in on this device.', 'modern-job-board'),
                    'passwordFailed' => __('The password could not be updated. Try again.', 'modern-job-board'),
                    'dismiss' => __('Dismiss', 'modern-job-board'),
                ),
            ));
        }

        if (is_singular('job_listing')) {
            wp_enqueue_script(
                'mjb-job-detail',
                MJB_URL . 'assets/js/mjb-job-detail.js',
                array(),
                MJB_VERSION,
                true
            );
        }
    }
}

// Initialize the plugin.
add_action('plugins_loaded', array('Modern_Job_Board', 'get_instance'));