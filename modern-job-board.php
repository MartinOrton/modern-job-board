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

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('mjb-style', MJB_URL . 'assets/css/mjb-style.css', array(), MJB_VERSION);
    }
}

// Initialize the plugin.
add_action('plugins_loaded', array('Modern_Job_Board', 'get_instance'));
