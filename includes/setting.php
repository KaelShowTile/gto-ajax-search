<?php
class GTO_AJAX_Search_Settings {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'glint_wc_ajax_search';
        
        add_action('admin_menu', [$this, 'add_settings_page']);
    }

    public function add_settings_page() {
        add_options_page(
            'GTO Ajax Search Settings',
            'GTO Ajax Search',
            'manage_options',
            'gto-ajax-search-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        // Get saved values
        $custom_post_types = $this->get_option('custom_post_type');
        $excluded_items = $this->get_option('exclude_from_search_result');
        $highest_priority = $this->get_option('highest_priority');
        $lowest_priority = $this->get_option('lowest_priority');
        ?>
        <div class="wrap">
            <h1>GTO Ajax Search Settings</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="gto_save_search_settings">
                <?php wp_nonce_field('gto_search_settings_nonce', '_wpnonce'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Custom Post Types</th>
                        <td>
                            <textarea 
                                name="custom_post_type" 
                                rows="5" 
                                cols="50"
                                placeholder="Enter one post type per line (e.g., product, product_variation)"
                            ><?php echo esc_textarea($custom_post_types); ?></textarea>
                            <p class="description">
                                Add additional post types to search (one per line). Default: "product".<br>
                                Examples: <code>product_variation</code>, <code>post</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Exclude from Results</th>
                        <td>
                            <textarea 
                                name="exclude_from_search_result" 
                                rows="5" 
                                cols="50"
                                placeholder="Enter IDs to exclude (one per line)"
                            ><?php echo esc_textarea($excluded_items); ?></textarea>
                            <p class="description">
                                Exclude specific products or categories from search results (one ID per line).<br>
                                Format: <code>product:123</code> or <code>category:456</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Highest Priority Items</th>
                        <td>
                            <textarea 
                                name="highest_priority" 
                                rows="5" 
                                cols="50"
                                placeholder="Enter IDs for highest priority (one per line)"
                            ><?php echo esc_textarea($highest_priority); ?></textarea>
                            <p class="description">
                                These items will appear first in search results.<br>
                                Format: <code>product:123</code> or <code>category:456</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Lowest Priority Items</th>
                        <td>
                            <textarea 
                                name="lowest_priority" 
                                rows="5" 
                                cols="50"
                                placeholder="Enter IDs for lowest priority (one per line)"
                            ><?php echo esc_textarea($lowest_priority); ?></textarea>
                            <p class="description">
                                These items will appear last in search results.<br>
                                Format: <code>product:123</code> or <code>category:456</code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function save_options() {
        //if (!isset($_POST['_wpnonce']) return;
        check_admin_referer('gto_search_settings_nonce', '_wpnonce');
        
        global $wpdb;
        
        $options = [
            'custom_post_type',
            'exclude_from_search_result',
            'highest_priority',
            'lowest_priority'
        ];
        
        foreach ($options as $option) {
            $value = isset($_POST[$option]) ? sanitize_textarea_field($_POST[$option]) : '';
            $wpdb->replace($this->table_name, [
                'option_name' => $option,
                'option_value' => $value
            ]);
        }
    }

    public function get_option($option_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$this->table_name} WHERE option_name = %s",
            $option_name
        ));
        return $result ? $result : '';
    }
}

// Handle form submission
add_action('admin_post_gto_save_search_settings', function() {
    $settings = new GTO_AJAX_Search_Settings();
    $settings->save_options();
    wp_safe_redirect(admin_url('options-general.php?page=gto-ajax-search-settings&saved=1'));
    exit;
});