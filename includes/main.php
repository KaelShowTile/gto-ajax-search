<?php
class GTO_AJAX_Search {

    private $settings;

    public function __construct(){
        $this->settings = new GTO_AJAX_Search_Settings();

        // AJAX Handlers
        add_action('wp_ajax_woocommerce_ajax_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_search', array($this, 'ajax_search'));
        add_action('wp_ajax_woocommerce_ajax_search_init', array($this, 'init_search_data'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_search_init', array($this, 'init_search_data'));
        add_action('wp_ajax_woocommerce_ajax_database_search', array($this, 'ajax_database_search'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_database_search', array($this, 'ajax_database_search'));
        add_action('wp_ajax_woocommerce_ajax_xml_search', array($this, 'ajax_xml_search'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_xml_search', array($this, 'ajax_xml_search'));
        add_action('wp_ajax_woocommerce_ajax_xml_local_search', array($this, 'ajax_xml_local_search'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_xml_local_search', array($this, 'ajax_xml_local_search'));

        //Search result template redirection
        add_filter('template_include', [$this, 'custom_search_template'], 999);

        // Add search shortcode
        add_shortcode('gto_ajax_search', array($this, 'gto_ajax_search_bar'));
        add_shortcode('gto_db_ajax_search', array($this, 'gto_ajax_database_search_bar'));
        add_shortcode('gto_xml_ajax_search', array($this, 'gto_ajax_xml_search_bar'));
        add_shortcode('gto_xml_local_ajax_search', array($this, 'gto_ajax_xml_local_search_bar'));

        // Schedule XML generation
        add_action('gto_generate_search_xml', array($this, 'generate_search_xml'));
        if (!wp_next_scheduled('gto_generate_search_xml')) {
            wp_schedule_event(time(), 'daily', 'gto_generate_search_xml');
        }
    }

    public function gto_ajax_search_bar() {
        ob_start();
        ?>
        <div class="woocommerce-ajax-search-container">
            <form role="search" method="get" class="woocommerce-ajax-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/search-icon.svg" class="search-icon">
                <input type="search" class="woocommerce-ajax-search-field" placeholder="<?php echo esc_attr__('Search products...', 'woocommerce'); ?>" value="" name="s" autocomplete="off" />
                <input type="hidden" name="post_type" value="product" />
                <input type="hidden" name="tiles_search_result" value="1" />
                <div class="woocommerce-ajax-search-results" data-base-url="<?php echo plugins_url('', dirname(__FILE__)); ?>">
                    <img id="loading-search-result" src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/loading-grey.svg">
                </div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function gto_ajax_database_search_bar() {
        ob_start();
        ?>
        <div class="woocommerce-ajax-search-container database-ajax">
            <form role="search" method="get" class="woocommerce-ajax-search-form database-ajax" action="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/search-icon.svg" class="search-icon">
                <input type="search" class="woocommerce-ajax-search-field database-ajax" placeholder="<?php echo esc_attr__('Search products...', 'woocommerce'); ?>" value="" name="s" autocomplete="off" />
                <input type="hidden" name="post_type" value="product" />
                <input type="hidden" name="tiles_search_result" value="1" />
                <div class="woocommerce-ajax-search-results database-ajax" data-base-url="<?php echo plugins_url('', dirname(__FILE__)); ?>">
                    <img id="loading-search-result" src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/loading-grey.svg">
                </div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function gto_ajax_xml_search_bar() {
        ob_start();
        ?>
        <div class="woocommerce-ajax-search-container xml-ajax">
            <form role="search" method="get" class="woocommerce-ajax-search-form xml-ajax" action="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/search-icon.svg" class="search-icon">
                <input type="search" class="woocommerce-ajax-search-field xml-ajax" placeholder="<?php echo esc_attr__('Search products...', 'woocommerce'); ?>" value="" name="s" autocomplete="off" />
                <input type="hidden" name="post_type" value="product" />
                <input type="hidden" name="tiles_search_result" value="1" />
                <div class="woocommerce-ajax-search-results xml-ajax" data-base-url="<?php echo plugins_url('', dirname(__FILE__)); ?>">
                    <img id="loading-search-result xml-ajax" src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/loading-grey.svg">
                </div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function gto_ajax_xml_local_search_bar() {
        ob_start();
        ?>
        <div class="woocommerce-ajax-search-container xml-local-ajax">
            <form role="search" method="get" class="woocommerce-ajax-search-form xml-local-ajax" action="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/search-icon.svg" class="search-icon">
                <input type="search" class="woocommerce-ajax-search-field xml-local-ajax" placeholder="<?php echo esc_attr__('Search products...', 'woocommerce'); ?>" value="" name="s" autocomplete="off" />
                <input type="hidden" name="post_type" value="product" />
                <input type="hidden" name="tiles_search_result" value="1" />
                <div class="woocommerce-ajax-search-results xml-local-ajax" data-base-url="<?php echo plugins_url('', dirname(__FILE__)); ?>">
                    <img id="loading-search-result xml-local-ajax" src="<?php echo plugins_url('', dirname(__FILE__)); ?>/img/loading-grey.svg">
                </div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function custom_search_template($template) {
        if (is_search() && isset($_GET['tiles_search_result']) && $_GET['tiles_search_result'] == '1') {
            // Check for theme overrides first
            $theme_template = locate_template('cht-search-results.php');
            if (!empty($theme_template)) {
                return $theme_template;
            }
            // Use plugin's default template
            return plugin_dir_path(__FILE__) . 'templates/search-results.php';
        }
        return $template;
    }

    private function prioritize_results($results) {
        $combined = [];
        
        // Add products with type info
        foreach ($results['products'] as $product) {
            $combined[] = array_merge($product, ['type' => 'product']);
        }
        
        // Add categories with type info
        foreach ($results['categories'] as $category) {
            $combined[] = array_merge($category, ['type' => 'category']);
        }
        
        // Sort by priority: high > normal > low
        usort($combined, function($a, $b) {
            $priority_order = ['high' => 1, 'normal' => 2, 'low' => 3];
            return $priority_order[$a['priority']] - $priority_order[$b['priority']];
        });
        
        // Apply 7-item limit
        $limited = array_slice($combined, 0, 7);
        
        // Rebuild results structure
        $prioritized_results = [
            'products' => [],
            'categories' => []
        ];
        
        foreach ($limited as $item) {
            if ($item['type'] === 'product') {
                unset($item['type']);
                $prioritized_results['products'][] = $item;
            } else {
                unset($item['type']);
                $prioritized_results['categories'][] = $item;
            }
        }
        
        return $prioritized_results;
    }
    
    private function get_priority_items($type) {
        $items = $this->settings->get_option($type);
        if (empty($items)) return [];
        
        $items = array_map('trim', explode("\n", $items));
        return array_filter($items, function($item) {
            return preg_match('/^(product|category):\d+$/', $item);
        });
    }

    public function init_search_data() {
        
        $data = array(
            'products' => array(),
            'categories' => array()
        );

        // Get excluded items
        $excluded = $this->get_expanded_excluded_items();

        // Get priority settings
        $priority = $this->get_expanded_priority_items();
        $highest_priority = $priority['highest'];
        $lowest_priority = $priority['lowest'];
        
        // Get post types to search
        $post_types = ['product'];
        $custom_types = $this->settings->get_option('custom_post_type');
        
        if ($custom_types) {
            $additional_types = array_map('trim', explode("\n", $custom_types));
            $post_types = array_merge($post_types, $additional_types);
        }

        // Query for products and custom post types
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        // Suitation of product, need to add support for other post type later
        $product_query = new WP_Query($args);
        $product_ids = $product_query->posts;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            if ( $product->is_in_stock() ) {
                $product_key = 'product:' . $product_id;
            
                // Skip explicitly excluded products
                if (in_array($product_key, $excluded)) continue;
                
                // Add priority flags
                $is_high_priority = in_array($product_key, $highest_priority);
                $is_low_priority = in_array($product_key, $lowest_priority);
                
                $data['products'][] = array(
                    'title' => $product->get_name(),
                    'url' => $product->get_permalink(),
                    'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                    'id' => $product_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            } 
        }

        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 0
        ));

        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_key = 'category:' . $category->term_id;
                
                // Skip excluded categories
                if (in_array($category_key, $excluded)) continue;
                
                // Add priority flags
                $is_high_priority = in_array($category_key, $highest_priority);
                $is_low_priority = in_array($category_key, $lowest_priority);
                
                $data['categories'][] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'count' => $category->count,
                    'id' => $category->term_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
        }

        wp_send_json_success($data);
    }

    private function get_expanded_priority_items() {
        $priority = [
            'highest' => [],
            'lowest' => []
        ];
        
        // Get highest priority items
        $highest = $this->settings->get_option('highest_priority');
        if ($highest) {
            $priority['highest'] = array_map('trim', explode("\n", $highest));
            $priority['highest'] = array_filter($priority['highest'], function($item) {
                return preg_match('/^(product|category):\d+$/', $item);
            });
        }
        
        // Get lowest priority items
        $lowest = $this->settings->get_option('lowest_priority');
        if ($lowest) {
            $priority['lowest'] = array_map('trim', explode("\n", $lowest));
            $priority['lowest'] = array_filter($priority['lowest'], function($item) {
                return preg_match('/^(product|category):\d+$/', $item);
            });
        }
        
        // Expand category priorities to include their products
        $this->expand_category_priorities($priority);
        
        // Resolve conflicts (remove from low if in high)
        $priority['lowest'] = array_diff($priority['lowest'], $priority['highest']);
        
        return $priority;
    }

    private function get_all_priority_items() {
        $priority = [
            'highest' => [],
            'lowest' => []
        ];
        
        // Get highest priority items
        $highest = $this->settings->get_option('highest_priority');
        if ($highest) {
            $priority['highest'] = array_map('trim', explode("\n", $highest));
        }
        
        // Get lowest priority items
        $lowest = $this->settings->get_option('lowest_priority');
        if ($lowest) {
            $priority['lowest'] = array_map('trim', explode("\n", $lowest));
        }
        
        // Expand category priorities to include their products
        $this->expand_category_priorities($priority);
        
        // Resolve conflicts (remove from low if in high)
        $priority['lowest'] = array_diff($priority['lowest'], $priority['highest']);
        
        return $priority;
    }

    private function expand_category_priorities(&$priority) {
        global $wpdb;
        
        // Process highest priority categories
        $highest_cats = array_filter($priority['highest'], function($item) {
            return strpos($item, 'category:') === 0;
        });
        
        foreach ($highest_cats as $item) {
            $cat_id = (int) str_replace('category:', '', $item);
            $product_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d", 
                $cat_id
            ));
            
            foreach ($product_ids as $product_id) {
                $priority['highest'][] = 'product:' . $product_id;
            }
        }
        
        // Process lowest priority categories
        $lowest_cats = array_filter($priority['lowest'], function($item) {
            return strpos($item, 'category:') === 0;
        });
        
        foreach ($lowest_cats as $item) {
            $cat_id = (int) str_replace('category:', '', $item);
            $product_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d", 
                $cat_id
            ));
            
            foreach ($product_ids as $product_id) {
                $priority['lowest'][] = 'product:' . $product_id;
            }
        }
    }

    public function get_expanded_excluded_items() {
        $excluded = $this->settings->get_option('exclude_from_search_result');
        if (empty($excluded)) return [];
        
        $items = array_map('trim', explode("\n", $excluded));
        $valid_items = array_filter($items, function($item) {
            return preg_match('/^(product|category):\d+$/', $item);
        });
        
        // Expand category exclusions to include their products
        $expanded = [];
        $excluded_cats = array_filter($valid_items, function($item) {
            return strpos($item, 'category:') === 0;
        });

        foreach ($excluded_cats as $item) {
            $expanded[] = $item;
            $cat_id = (int) str_replace('category:', '', $item);
            global $wpdb;
            $product_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT object_id FROM {$wpdb->term_relationships} 
                WHERE term_taxonomy_id = %d", 
                $cat_id
            ));
            
            foreach ($product_ids as $product_id) {
                $expanded[] = 'product:' . $product_id;
            }
        }
        
        // Add excluded products that aren't in categories
        $excluded_products = array_filter($valid_items, function($item) {
            return strpos($item, 'product:') === 0;
        });
        
        foreach ($excluded_products as $item) {
            $expanded[] = $item;
        }
        
        return array_unique($expanded);
    }

    public function ajax_search(){
        //check_ajax_referer('woocommerce_ajax_search_nonce', 'security');

        $search_term = sanitize_text_field($_POST['search_term']);

        if (strlen($search_term) < 3) {
            wp_send_json_error(array('message' => __('Minimum 3 characters required', 'woocommerce')));
        }

        $results = array(
            'products' => array(),
            'categories' => array()
        );

        // Get excluded items
        $excluded = $this->get_expanded_excluded_items();

        // Get priority settings
        $priority = $this->get_expanded_priority_items();
        $highest_priority = $priority['highest'];
        $lowest_priority = $priority['lowest'];
        
        // Get post types to search
        $post_types = ['product'];
        $custom_types = $this->settings->get_option('custom_post_type');
        
        if ($custom_types) {
            $additional_types = array_map('trim', explode("\n", $custom_types));
            $post_types = array_merge($post_types, $additional_types);
        }

        // Product search
        $product_args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            's' => $search_term,
            'posts_per_page' => 20 // Get more than 7 for prioritization
        );

        $product_query = new WP_Query($product_args);

        if ($product_query->have_posts()) {
            while ($product_query->have_posts()) {
                $product_query->the_post();
                $product_id = get_the_ID();
                $product_key = 'product:' . $product_id;
                
                // Skip excluded products
                if (in_array($product_key, $excluded)) continue;
                
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                // Add priority flags
                $is_high_priority = in_array($product_key, $highest_priority);
                $is_low_priority = in_array($product_key, $lowest_priority);
                
                $results['products'][] = array(
                    'title' => $product->get_name(),
                    'url' => $product->get_permalink(),
                    'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                    'id' => $product_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
            wp_reset_postdata();
        }

        // Category search
        $category_args = array(
            'taxonomy' => 'product_cat',
            'name__like' => $search_term,
            'hide_empty' => true,
            'number' => 20 // Get more than 7 for prioritization
        );

        $categories = get_terms($category_args);

        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_key = 'category:' . $category->term_id;
                
                // Skip excluded categories
                if (in_array($category_key, $excluded)) continue;
                
                // Add priority flags
                $is_high_priority = in_array($category_key, $highest_priority);
                $is_low_priority = in_array($category_key, $lowest_priority);
                
                $results['categories'][] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'count' => $category->count,
                    'id' => $category->term_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
        }

        // Apply prioritization
        $results = $this->prioritize_results($results);
        
        wp_send_json_success($results);
    }

    public function ajax_database_search(){
        //check_ajax_referer('woocommerce_ajax_search_nonce', 'security');

        $search_term = sanitize_text_field($_POST['search_term']);

        if (strlen($search_term) < 3) {
            wp_send_json_error(array('message' => __('Minimum 3 characters required', 'woocommerce')));
        }

        $results = array(
            'products' => array(),
            'categories' => array()
        );

        // Get excluded items
        $excluded = $this->get_expanded_excluded_items();

        // Get priority settings
        $priority = $this->get_expanded_priority_items();
        $highest_priority = $priority['highest'];
        $lowest_priority = $priority['lowest'];

        // Get post types to search
        $post_types = ['product'];
        $custom_types = $this->settings->get_option('custom_post_type');

        if ($custom_types) {
            $additional_types = array_map('trim', explode("\n", $custom_types));
            $post_types = array_merge($post_types, $additional_types);
        }

        // Product search
        $product_args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            's' => $search_term,
            'posts_per_page' => 20, // Get more than 7 for prioritization
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            )
        );

        $product_query = new WP_Query($product_args);
        
        if ($product_query->have_posts()) {
            while ($product_query->have_posts()) {
                $product_query->the_post();
                $product_id = get_the_ID();
                $product_key = 'product:' . $product_id;

                // Skip excluded products
                if (in_array($product_key, $excluded)) continue;

                $product = wc_get_product($product_id);
                if (!$product || !$product->is_in_stock()) continue;

                // Add priority flags
                $is_high_priority = in_array($product_key, $highest_priority);
                $is_low_priority = in_array($product_key, $lowest_priority);

                $results['products'][] = array(
                    'title' => $product->get_name(),
                    'url' => $product->get_permalink(),
                    'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                    'id' => $product_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
            wp_reset_postdata();
        }

        // Category search
        $category_args = array(
            'taxonomy' => 'product_cat',
            'name__like' => $search_term,
            'hide_empty' => true,
            'number' => 20 // Get more than 7 for prioritization
        );

        $categories = get_terms($category_args);

        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_key = 'category:' . $category->term_id;

                // Skip excluded categories
                if (in_array($category_key, $excluded)) continue;

                // Add priority flags
                $is_high_priority = in_array($category_key, $highest_priority);
                $is_low_priority = in_array($category_key, $lowest_priority);

                $results['categories'][] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'count' => $category->count,
                    'id' => $category->term_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
        }

        // Apply prioritization
        $results = $this->prioritize_results($results);

        wp_send_json_success($results);
    }

    public function generate_search_xml() {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/gto-search-data.xml';

        // Get all data similar to init_search_data but for XML
        $data = array(
            'products' => array(),
            'categories' => array()
        );

        // Get excluded items
        $excluded = $this->get_expanded_excluded_items();

        // Get priority settings
        $priority = $this->get_expanded_priority_items();
        $highest_priority = $priority['highest'];
        $lowest_priority = $priority['lowest'];

        // Get post types to search
        $post_types = ['product'];
        $custom_types = $this->settings->get_option('custom_post_type');

        if ($custom_types) {
            $additional_types = array_map('trim', explode("\n", $custom_types));
            $post_types = array_merge($post_types, $additional_types);
        }

        // Query for products and custom post types
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $product_query = new WP_Query($args);
        $product_ids = $product_query->posts;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            if ($product->is_in_stock()) {
                $product_key = 'product:' . $product_id;

                // Skip explicitly excluded products
                if (in_array($product_key, $excluded)) continue;

                // Add priority flags
                $is_high_priority = in_array($product_key, $highest_priority);
                $is_low_priority = in_array($product_key, $lowest_priority);

                $data['products'][] = array(
                    'title' => $product->get_name(),
                    'url' => $product->get_permalink(),
                    'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                    'id' => $product_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
        }

        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 0
        ));

        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_key = 'category:' . $category->term_id;

                // Skip excluded categories
                if (in_array($category_key, $excluded)) continue;

                // Add priority flags
                $is_high_priority = in_array($category_key, $highest_priority);
                $is_low_priority = in_array($category_key, $lowest_priority);

                $data['categories'][] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'count' => $category->count,
                    'id' => $category->term_id,
                    'priority' => $is_high_priority ? 'high' : ($is_low_priority ? 'low' : 'normal')
                );
            }
        }

        // Generate XML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><search_data></search_data>');

        // Add products
        $products_xml = $xml->addChild('products');
        foreach ($data['products'] as $product) {
            $product_xml = $products_xml->addChild('product');
            $product_xml->addChild('title', htmlspecialchars($product['title']));
            $product_xml->addChild('url', $product['url']);
            $product_xml->addChild('image_url', $product['image_url']);
            $product_xml->addChild('id', $product['id']);
            $product_xml->addChild('priority', $product['priority']);
        }

        // Add categories
        $categories_xml = $xml->addChild('categories');
        foreach ($data['categories'] as $category) {
            $category_xml = $categories_xml->addChild('category');
            $category_xml->addChild('title', htmlspecialchars($category['title']));
            $category_xml->addChild('url', $category['url']);
            $category_xml->addChild('count', $category['count']);
            $category_xml->addChild('id', $category['id']);
            $category_xml->addChild('priority', $category['priority']);
        }

        // Save XML file
        $xml->asXML($xml_file);
    }

    public function ajax_xml_search(){
        //check_ajax_referer('woocommerce_ajax_search_nonce', 'security');

        $search_term = sanitize_text_field($_POST['search_term']);

        if (strlen($search_term) < 3) {
            wp_send_json_error(array('message' => __('Minimum 3 characters required', 'woocommerce')));
        }

        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/gto-search-data.xml';

        if (!file_exists($xml_file)) {
            // Generate XML if it doesn't exist
            $this->generate_search_xml();
        }

        // Load XML
        $xml = simplexml_load_file($xml_file);
        if (!$xml) {
            wp_send_json_error(array('message' => __('Search data not available', 'woocommerce')));
        }

        $results = array(
            'products' => array(),
            'categories' => array()
        );

        // Search products
        foreach ($xml->products->product as $product) {
            $title = (string)$product->title;
            if (stripos($title, $search_term) !== false) {
                $results['products'][] = array(
                    'title' => $title,
                    'url' => (string)$product->url,
                    'image_url' => (string)$product->image_url,
                    'id' => (int)$product->id,
                    'priority' => (string)$product->priority
                );
            }
        }

        // Search categories
        foreach ($xml->categories->category as $category) {
            $title = (string)$category->title;
            if (stripos($title, $search_term) !== false) {
                $results['categories'][] = array(
                    'title' => $title,
                    'url' => (string)$category->url,
                    'count' => (int)$category->count,
                    'id' => (int)$category->id,
                    'priority' => (string)$category->priority
                );
            }
        }

        // Apply prioritization
        $results = $this->prioritize_results($results);

        wp_send_json_success($results);
    }

    public function ajax_xml_local_search(){
        //check_ajax_referer('woocommerce_ajax_search_nonce', 'security');

        $search_term = sanitize_text_field($_POST['search_term']);

        if (strlen($search_term) < 3) {
            wp_send_json_error(array('message' => __('Minimum 3 characters required', 'woocommerce')));
        }

        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/gto-search-data.xml';
        $xml_url = $upload_dir['baseurl'] . '/gto-search-data.xml';

        // Check if XML exists on server, generate if not
        if (!file_exists($xml_file)) {
            $this->generate_search_xml();
        }

        // Return XML URL for client to download/cache
        wp_send_json_success(array(
            'xml_url' => $xml_url,
            'last_modified' => filemtime($xml_file)
        ));
    }

}
