<?php
/*
Plugin Name: Recent Posts Shortcode
Description: Выводит список последних постов с помощью шорткода [recent_posts]
Version: 1.0
Author: Kaz Kadalashvili
*/

require __DIR__ . '/vendor/autoload.php';

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class RecentPostsShortcode {
    private $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function init() {
        add_shortcode('recent_posts', [$this, 'recent_posts_shortcode']);
    }

    public function recent_posts_shortcode($atts) {
        $posts_count = get_option('recent_posts_count', 10);
        
        try {
            if (!class_exists('WP_Query')) {
                throw new Exception('Class WP_Query does not exist.');
            }

            $args = [
                'post_type' => 'post',
                'posts_per_page' => $posts_count,
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            
            $query = new WP_Query($args);
            
            ob_start();
            
            if ($query->have_posts()) {
                echo '<ul class="recent-posts">';
                while ($query->have_posts()) {
                    $query->the_post();
                    echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
                }
                echo '</ul>';
            } else {
                echo 'No posts found.';
            }
            
            wp_reset_postdata();
            
            return ob_get_clean();
        } catch (Exception $e) {
            $this->logger->error('Error in recent_posts_shortcode: ' . $e->getMessage());
            return 'An error occurred while fetching the recent posts.';
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'Recent Posts Settings',
            'Recent Posts',
            'manage_options',
            'recent-posts-shortcode',
            [$this, 'settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('recent_posts_shortcode_settings', 'recent_posts_count');
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Recent Posts Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('recent_posts_shortcode_settings');
                do_settings_sections('recent_posts_shortcode_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Number of posts to display</th>
                        <td>
                            <input type="number" name="recent_posts_count" value="<?php echo esc_attr(get_option('recent_posts_count', 10)); ?>" min="1" max="100" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

function initialize_recent_posts_shortcode() {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $logger = new Logger('recent_posts_shortcode');
    $logger->pushHandler(new StreamHandler($log_dir . '/plugin.log', Logger::ERROR));

    new RecentPostsShortcode($logger);
}

add_action('plugins_loaded', 'initialize_recent_posts_shortcode');
