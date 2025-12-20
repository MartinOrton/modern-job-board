<?php
/**
 * Plugin Name: Modern Job Board
 * Plugin URI: https://github.com/MartinOrton/modern-job-board
 * Description: A lightweight job board plugin for WordPress.
 * Version: 1.0.0
 * Author: Martin Orton
 * Author URI: https://www.martinorton.com
 * Text Domain: modern-job-board
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('MJB_VERSION', '1.0.0');
define('MJB_PATH', plugin_dir_path(__FILE__));
define('MJB_URL', plugin_dir_url(__FILE__));

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
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

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('mjb-style', MJB_URL . 'assets/css/mjb-style.css', array(), MJB_VERSION);

        wp_enqueue_script('mjb-ajax-search', MJB_URL . 'assets/js/mjb-ajax-search.js', array('jquery'), MJB_VERSION, true);
        wp_localize_script('mjb-ajax-search', 'mjb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mjb_search_nonce')
        ));
    }
}

// Initialize the plugin.
add_action('plugins_loaded', array('Modern_Job_Board', 'get_instance'));
