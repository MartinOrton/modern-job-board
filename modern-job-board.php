<?php
/**
 * Plugin Name: Modern Job Board
 * Plugin URI: https://github.com/MartinOrton/modern-job-board
 * Description: A lightweight job board plugin for WordPress.
 * Version: 1.9.0
 * Author: Martin Orton
 * Author URI: https://www.martinorton.com
 * Text Domain: modern-job-board
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('MJB_VERSION', '1.9.0');
define('MJB_PATH', plugin_dir_path(__FILE__));
define('MJB_URL', plugin_dir_url(__FILE__));

require_once MJB_PATH . 'includes/class-mjb-resumes.php';
require_once MJB_PATH . 'includes/class-mjb-activator.php';
require_once MJB_PATH . 'includes/class-mjb-notices.php';
require_once MJB_PATH . 'includes/class-mjb-page-resolver.php';
require_once MJB_PATH . 'includes/class-mjb-job-routes.php';
require_once MJB_PATH . 'includes/class-mjb-application-guard.php';
require_once MJB_PATH . 'includes/class-mjb-recaptcha.php';
require_once MJB_PATH . 'includes/class-mjb-job-importer.php';
require_once MJB_PATH . 'includes/class-mjb-xml-importer.php';
require_once MJB_PATH . 'includes/class-mjb-page-wizard.php';
require_once MJB_PATH . 'includes/class-mjb-application-status.php';
require_once MJB_PATH . 'includes/class-mjb-rest-api-v2.php';
require_once MJB_PATH . 'includes/class-mjb-data-grid.php';
require_once MJB_PATH . 'includes/class-mjb-blocks.php';
require_once MJB_PATH . 'includes/class-mjb-analytics.php';
require_once MJB_PATH . 'includes/class-mjb-webhook-queue.php';
require_once MJB_PATH . 'includes/class-mjb-webhooks.php';

register_activation_hook(__FILE__, array('MJB_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('MJB_Activator', 'deactivate'));

// Include core classes.
require_once MJB_PATH . 'includes/class-mjb-cpt.php';
require_once MJB_PATH . 'includes/class-mjb-shortcodes.php';
require_once MJB_PATH . 'includes/class-mjb-admin.php';
require_once MJB_PATH . 'includes/class-mjb-template-loader.php';
require_once MJB_PATH . 'includes/class-mjb-applications.php';
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
        MJB_Page_Resolver::init();
        MJB_Job_Routes::init();
        MJB_Page_Wizard::init();
        MJB_Analytics::init();
        MJB_Webhooks::init();
        MJB_Webhook_Queue::init();
        MJB_Blocks::init();
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));

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

        // Initialize Candidate Dashboard
        require_once MJB_PATH . 'includes/class-mjb-candidate-dashboard.php';
        $candidate_dashboard = new MJB_Candidate_Dashboard();
        $candidate_dashboard->init();

        // Initialize WooCommerce Integration
        if (class_exists('WooCommerce')) {
            require_once MJB_PATH . 'includes/class-mjb-woocommerce.php';
            $mjb_woocommerce = new MJB_WooCommerce();
            $mjb_woocommerce->init();
        }

        // Initialize Custom Fields
        require_once MJB_PATH . 'includes/class-mjb-custom-fields.php';
        global $mjb_custom_fields;
        $mjb_custom_fields = new MJB_Custom_Fields();
        $mjb_custom_fields->init();

        // Initialize Tools (CSV Import/Export)
        require_once MJB_PATH . 'includes/class-mjb-tools.php';
        $mjb_tools = new MJB_Tools();
        $mjb_tools->init();

        // Initialize Integrations (Feeds & API)
        require_once MJB_PATH . 'includes/class-mjb-feeds.php';
        $mjb_feeds = new MJB_Feeds();
        $mjb_feeds->init();

        require_once MJB_PATH . 'includes/class-mjb-rest-api.php';
        $mjb_api = new MJB_REST_API();
        $mjb_api->init();

        $mjb_api_v2 = new MJB_REST_API_V2();
        $mjb_api_v2->init();

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
        if (is_singular(array('job_listing', 'company')) || is_post_type_archive('job_listing') || is_tax(array('job_type', 'job_category', 'job_location'))) {
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

        if ($this->should_enqueue_recaptcha()) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }

        wp_enqueue_script('mjb-ajax-search', MJB_URL . 'assets/js/mjb-ajax-search.js', array('jquery'), MJB_VERSION, true);
        wp_localize_script('mjb-ajax-search', 'mjb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mjb_search_nonce'),
            'jobs_search_base' => trailingslashit(MJB_Job_Routes::build_url()),
            'jobs_api_search_base' => trailingslashit(MJB_Job_Routes::build_url(array(), array('rest' => true))),
        ));
    }
}

// Initialize the plugin.
add_action('plugins_loaded', array('Modern_Job_Board', 'get_instance'));