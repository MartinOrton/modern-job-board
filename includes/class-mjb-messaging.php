<?php
/**
 * In-app messaging employer ↔ candidate (#37).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Messaging
{
    const CPT = 'mjb_message';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_cpt'));
        add_action('wp_ajax_mjb_send_message', array(__CLASS__, 'ajax_send'));
        add_action('wp_ajax_mjb_list_messages', array(__CLASS__, 'ajax_list'));
        add_shortcode('mjb_messages', array(__CLASS__, 'render_inbox'));
    }

    /**
     * Private message CPT.
     */
    public static function register_cpt()
    {
        register_post_type(self::CPT, array(
            'labels' => array('name' => __('Messages', 'modern-job-board')),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title', 'editor', 'author'),
        ));
    }

    /**
     * @param string $body
     * @return string
     */
    private static function title_from_body($body)
    {
        $plain = wp_strip_all_tags($body);
        if (function_exists('wp_trim_words')) {
            return wp_trim_words($plain, 8, '…');
        }
        $words = preg_split('/\s+/', $plain, 9);
        if (is_array($words) && count($words) > 8) {
            array_pop($words);
            return implode(' ', $words) . '…';
        }
        return $plain;
    }

    /**
     * @param int    $from
     * @param int    $to
     * @param string $body
     * @param int    $job_id
     * @return int|WP_Error
     */
    public static function send($from, $to, $body, $job_id = 0)
    {
        $from = (int) $from;
        $to = (int) $to;
        $body = trim(wp_kses_post($body));
        if ($from <= 0 || $to <= 0 || $body === '') {
            return new WP_Error('mjb_msg_invalid', __('Invalid message.', 'modern-job-board'));
        }
        if ($from === $to) {
            return new WP_Error('mjb_msg_self', __('Cannot message yourself.', 'modern-job-board'));
        }

        $id = wp_insert_post(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'post_author' => $from,
            'post_title' => self::title_from_body($body),
            'post_content' => $body,
        ), true);

        if (is_wp_error($id)) {
            return $id;
        }

        update_post_meta($id, '_mjb_to', $to);
        update_post_meta($id, '_mjb_job_id', (int) $job_id);
        update_post_meta($id, '_mjb_read', '0');

        $recipient = get_userdata($to);
        $send_copy = $recipient && $recipient->user_email;
        if ($send_copy && class_exists('MJB_Candidate_Account') && !MJB_Candidate_Account::allows_email('messages', $to)) {
            $send_copy = false;
        }
        if ($send_copy) {
            $subject = sprintf(__('[%s] New message', 'modern-job-board'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
            $message = sprintf(__("You have a new message on %s.\n\n%s", 'modern-job-board'), home_url('/'), wp_strip_all_tags($body));
            wp_mail($recipient->user_email, $subject, $message);
        }

        return (int) $id;
    }

    /**
     * AJAX send.
     */
    public static function ajax_send()
    {
        check_ajax_referer('mjb_messages', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login_required'), 401);
        }
        $to = isset($_POST['to']) ? (int) $_POST['to'] : 0;
        $body = isset($_POST['body']) ? wp_unslash($_POST['body']) : '';
        $job_id = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
        $id = self::send(get_current_user_id(), $to, $body, $job_id);
        if (is_wp_error($id)) {
            wp_send_json_error(array('message' => $id->get_error_message()), 400);
        }
        wp_send_json_success(array('id' => $id));
    }

    /**
     * AJAX list thread.
     */
    public static function ajax_list()
    {
        check_ajax_referer('mjb_messages', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login_required'), 401);
        }
        $uid = get_current_user_id();
        $with = isset($_GET['with']) ? (int) $_GET['with'] : 0;

        $q = new WP_Query(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_mjb_to', 'value' => $uid),
            ),
        ));

        $items = array();
        foreach ($q->posts as $post) {
            $to = (int) get_post_meta($post->ID, '_mjb_to', true);
            $from = (int) $post->post_author;
            if ($with > 0 && $from !== $with && $to !== $with) {
                continue;
            }
            if ($from !== $uid && $to !== $uid) {
                continue;
            }
            if ($to === $uid) {
                update_post_meta($post->ID, '_mjb_read', '1');
            }
            $items[] = array(
                'id' => $post->ID,
                'from' => $from,
                'to' => $to,
                'body' => $post->post_content,
                'date' => get_the_date('c', $post),
            );
        }
        wp_send_json_success(array('items' => $items));
    }

    /**
     * Inbox shortcode.
     *
     * @return string
     */
    public static function render_inbox()
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Log in to view messages.', 'modern-job-board') . '</p>';
        }
        $uid = get_current_user_id();
        $received = get_posts(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 30,
            'meta_key' => '_mjb_to',
            'meta_value' => $uid,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        ob_start();
        echo '<div class="mjb-messages">';
        echo '<h3>' . esc_html__('Messages', 'modern-job-board') . '</h3>';
        if (empty($received)) {
            echo '<p>' . esc_html__('No messages yet.', 'modern-job-board') . '</p>';
        } else {
            echo '<ul class="mjb-messages__list">';
            foreach ($received as $msg) {
                $from = get_userdata((int) $msg->post_author);
                $name = $from ? $from->display_name : __('User', 'modern-job-board');
                echo '<li><strong>' . esc_html($name) . '</strong>: ' . esc_html(wp_trim_words(wp_strip_all_tags($msg->post_content), 24)) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
