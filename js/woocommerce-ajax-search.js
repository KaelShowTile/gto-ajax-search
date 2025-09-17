jQuery(document).ready(function($) {
    const CACHE_KEY = 'gto_search_cache';
    const container = $('.woocommerce-ajax-search-container');
    const searchField = $('.woocommerce-ajax-search-field');
    const resultsContainer = $('.woocommerce-ajax-search-results');
    let searchTimer;
    let searchData = null;
    var baseUrl = $('.woocommerce-ajax-search-results').data('base-url');

    // Initialize search data
    function initSearch() {
        const cachedData = localStorage.getItem(CACHE_KEY);
        const timestamp = localStorage.getItem(CACHE_KEY + '_ts');
        const now = Math.floor(Date.now() / 1000);
        
        // Use cache if it's less than 1 hour old
        if (cachedData && timestamp && (now - timestamp < 259200)) {
            try {
                searchData = JSON.parse(cachedData);
                console.log("has cache data...");
                return;
            } catch(e) {
                console.error('Cache parse error', e);
            }
        }
        
        // Fetch fresh data
        $.ajax({
            url: woocommerce_ajax_search_params.ajax_url,
            type: 'POST',
            data: {
                action: 'woocommerce_ajax_search_init',
                security: woocommerce_ajax_search_params.nonce
            },
            success: (response) => {
                if (response.success) {
                    searchData = response.data;
                    localStorage.setItem(CACHE_KEY, JSON.stringify(searchData));
                    localStorage.setItem(CACHE_KEY + '_ts', Math.floor(Date.now() / 1000));
                }
            },
            error: () => console.log('Data load failed - using AJAX fallback')
        });
    }

    // Prioritize results for local search
    function prioritizeResults(results) {

        let combined = [];
        
        results.products.forEach(product => {
            combined.push({...product, type: 'product'});
        });
        
        results.categories.forEach(category => {
            combined.push({...category, type: 'category'});
        });
        
        combined.sort((a, b) => {
            const priorityOrder = {high: 1, normal: 2, low: 3};
            return priorityOrder[a.priority] - priorityOrder[b.priority];
        });
        
        combined = combined.slice(0, 7);
        
        // Rebuild results structure
        const prioritized = {
            products: [],
            categories: []
        };
        
        combined.forEach(item => {
            if (item.type === 'product') {
                const {type, ...clean} = item;
                prioritized.products.push(clean);
            } else {
                const {type, ...clean} = item;
                prioritized.categories.push(clean);
            }
        });
        
        return prioritized;
    }

    // Local search function
    function localSearch(term) {
        term = term.toLowerCase();
        let results = { products: [], categories: [] };
        
        if (!searchData) return results;
        
        // Filter products
        results.products = searchData.products.filter(p => 
            p.title.toLowerCase().includes(term)
        );
        
        // Filter categories
        results.categories = searchData.categories.filter(c => 
            c.title.toLowerCase().includes(term)
        );
        
        // Apply prioritization
        return prioritizeResults(results);
    }

    // Display results
    function displayResults(results) {
        let html = '';

        if (results.products.length > 0 || results.categories.length > 0) {
            // Products section
            if (results.products.length > 0) {
                html += '<div class="results-section"><h4>Products</h4><ul>';
                results.products.forEach(product => {
                    html += `<li><a href="${product.url}"><img src="${product.image_url}" alt="${product.title}"><span>${product.title}</span></a></li>`;
                });
                html += '</ul></div>';
            }

            // Categories section
            if (results.categories.length > 0) {
                html += '<div class="results-section category-result-section"><h4>Categories</h4><ul>';
                results.categories.forEach(category => {
                    html += `<li><a href="${category.url}">All Products in<span>${category.title}</span> <span class="count">(${category.count})</span></a></li>`;
                });
                html += '</ul></div>';
            }
        } else {
            html = '<div class="no-results"><?php echo esc_js(__("No results found", "woocommerce")); ?></div>';
        }

        resultsContainer.html(html).show();
    }

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!container.is(e.target) && container.has(e.target).length === 0) {
            resultsContainer.hide();
        }
    });

    // Search input handler
    searchField.on('input', function() {
        const searchTerm = $(this).val().trim();
        clearTimeout(searchTimer);
        resultsContainer.hide().empty();

        if (!searchTerm) return;
        
        if (searchTerm.length < woocommerce_ajax_search_params.min_characters) {
            resultsContainer.html(`<div class="no-results">${woocommerce_ajax_search_params.min_chars_message}</div>`).show();
            return;
        }

        searchTimer = setTimeout(() => {
            //resultsContainer.html('<div class="loading"><?php echo esc_js(__("Searching...", "woocommerce")); ?></div>').show();
            let loadingIcom = `<img id="loading-search-result" src="${baseUrl}/img/loading-grey.svg">`
            resultsContainer.html(loadingIcom).show();
            
            // Try local search first
            if (searchData) {
                const results = localSearch(searchTerm);
                displayResults(results);
            } 
            // Fallback to AJAX
            else {
                $.ajax({
                    url: woocommerce_ajax_search_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'woocommerce_ajax_search',
                        search_term: searchTerm,
                        security: woocommerce_ajax_search_params.nonce
                    },
                    success: (response) => {
                        if (response.success) displayResults(response.data);
                        else resultsContainer.html('<div class="no-results"></div>').show();
                    },
                    error: () => {
                        resultsContainer.html('<div class="no-results"><?php echo esc_js(__("Search error", "woocommerce")); ?></div>').show();
                    }
                });
            }
        }, 300);
    });

    // Initialize on page load
    initSearch();
    
    // Handle result clicks
    resultsContainer.on('click', 'a', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
    });

    //Handle the search result template
    $('.woocommerce-ajax-search-form').on('submit', function(e) {
        if ($('.woocommerce-ajax-search-results:visible').length > 0) {
            e.preventDefault();
            
            // Add a small delay to allow redirection
            setTimeout(() => {
                window.location.href = $(this).attr('action') + '?' + $(this).serialize();
            }, 100);
        }
    });

    // Clear results on actual submission
    searchField.on('keydown', function(e) {
        if (e.key === 'Enter' && $('.woocommerce-ajax-search-results:visible').length === 0) {
            resultsContainer.hide().empty();
        }
    });
});




