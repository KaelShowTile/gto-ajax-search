jQuery(document).ready(function($) {
    const CACHE_KEY = 'gto_search_cache';
    const XML_CACHE_KEY = 'gto_xml_cache';
    const containers = $('.woocommerce-ajax-search-container');
    const searchFields = $('.woocommerce-ajax-search-field');
    let searchTimer;
    let searchData = null;
    let xmlData = null;
    var baseUrl = $('.woocommerce-ajax-search-results').first().data('base-url');

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

    // Initialize XML data
    function initXmlSearch() {
        const cachedXmlData = localStorage.getItem(XML_CACHE_KEY);
        const xmlTimestamp = localStorage.getItem(XML_CACHE_KEY + '_ts');
        const now = Math.floor(Date.now() / 1000);

        // Check if we need to fetch XML info from server
        $.ajax({
            url: woocommerce_ajax_search_params.ajax_url,
            type: 'POST',
            data: {
                action: 'woocommerce_ajax_xml_local_search',
                search_term: 'dummy' // Just to get XML URL and timestamp
            },
            success: (response) => {
                if (response.success) {
                    const serverLastModified = response.data.last_modified;
                    const xmlUrl = response.data.xml_url;

                    // Check if local XML is up to date
                    if (!cachedXmlData || !xmlTimestamp || (serverLastModified > xmlTimestamp)) {
                        // Download fresh XML
                        fetch(xmlUrl)
                            .then(response => response.text())
                            .then(xmlText => {
                                try {
                                    const parser = new DOMParser();
                                    const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
                                    xmlData = parseXmlToJson(xmlDoc);
                                    localStorage.setItem(XML_CACHE_KEY, JSON.stringify(xmlData));
                                    localStorage.setItem(XML_CACHE_KEY + '_ts', serverLastModified);
                                    // Update any containers showing loading message
                                    updateXmlLoadingContainers();
                                    //console.log('XML data updated');
                                } catch(e) {
                                    console.error('XML parse error', e);
                                }
                            })
                            .catch(error => console.error('XML download failed', error));
                    } else {
                        // Use cached XML
                        try {
                            xmlData = JSON.parse(cachedXmlData);
                            // Update any containers showing loading message
                            updateXmlLoadingContainers();
                            //console.log('Using cached XML data');
                        } catch(e) {
                            console.error('Cached XML parse error', e);
                        }
                    }
                }
            },
            error: () => console.log('XML info fetch failed')
        });
    }

    // Update containers that are showing XML loading message
    function updateXmlLoadingContainers() {
        containers.each(function() {
            const container = $(this);
            const resultsContainer = container.find('.woocommerce-ajax-search-results');
            const form = container.find('.woocommerce-ajax-search-form');

            if (form.hasClass('xml-local-ajax') &&  (resultsContainer.html().includes('Loading search data') || resultsContainer.html().includes('loading-search-result'))) {
                const searchTerm = $('.woocommerce-ajax-search-field.xml-local-ajax').val().trim();
                if(searchTerm.length > 2){
                    performSearch(searchTerm, 'woocommerce_ajax_xml_local_search', resultsContainer);
                }
            }
        });
    }

    // Parse XML to JSON format similar to searchData
    function parseXmlToJson(xmlDoc) {
        const data = {
            products: [],
            categories: []
        };

        // Parse products
        const products = xmlDoc.getElementsByTagName('product');
        for (let i = 0; i < products.length; i++) {
            const product = products[i];
            data.products.push({
                title: product.getElementsByTagName('title')[0]?.textContent || '',
                url: product.getElementsByTagName('url')[0]?.textContent || '',
                image_url: product.getElementsByTagName('image_url')[0]?.textContent || '',
                id: parseInt(product.getElementsByTagName('id')[0]?.textContent || '0'),
                priority: product.getElementsByTagName('priority')[0]?.textContent || 'normal'
            });
        }

        // Parse categories
        const categories = xmlDoc.getElementsByTagName('category');
        for (let i = 0; i < categories.length; i++) {
            const category = categories[i];
            data.categories.push({
                title: category.getElementsByTagName('title')[0]?.textContent || '',
                url: category.getElementsByTagName('url')[0]?.textContent || '',
                count: parseInt(category.getElementsByTagName('count')[0]?.textContent || '0'),
                id: parseInt(category.getElementsByTagName('id')[0]?.textContent || '0'),
                priority: category.getElementsByTagName('priority')[0]?.textContent || 'normal'
            });
        }

        return data;
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

    // Local XML search function
    function localXmlSearch(term) {
        term = term.toLowerCase();
        let results = { products: [], categories: [] };

        if (!xmlData) return results;

        // Filter products
        results.products = xmlData.products.filter(p =>
            p.title.toLowerCase().includes(term)
        );

        // Filter categories
        results.categories = xmlData.categories.filter(c =>
            c.title.toLowerCase().includes(term)
        );

        // Apply prioritization
        return prioritizeResults(results);
    }

    // Display results in specific container
    function displayResults(results, container) {
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

        container.html(html).show();
    }

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        containers.each(function() {
            const container = $(this);
            const resultsContainer = container.find('.woocommerce-ajax-search-results');
            if (!container.is(e.target) && container.has(e.target).length === 0) {
                resultsContainer.hide();
            }
        });
    });

    // Perform search for specific type and container
    function performSearch(searchTerm, action, resultsContainer) {
        let loadingIcon = `<img id="loading-search-result" src="${baseUrl}/img/loading-grey.svg">`;
        resultsContainer.html(loadingIcon).show();

        if (action === 'woocommerce_ajax_search' && searchData) {
            // Local search for original method
            const results = localSearch(searchTerm);
            displayResults(results, resultsContainer);
        } else if (action === 'woocommerce_ajax_xml_local_search') {
            // Local XML search for combined method
            if (xmlData) {
                const results = localXmlSearch(searchTerm);
                displayResults(results, resultsContainer);
            } else {
                // XML data not loaded yet, show loading message
                resultsContainer.html(loadingIcon).show();
            }
        } else {
            // AJAX search for database or XML
            $.ajax({
                url: woocommerce_ajax_search_params.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    search_term: searchTerm,
                    //security: woocommerce_ajax_search_params.nonce
                },
                success: (response) => {
                    if (response.success) displayResults(response.data, resultsContainer);
                    else resultsContainer.html('<div class="no-results"></div>').show();
                },
                error: () => {
                    resultsContainer.html('<div class="no-results"><?php echo esc_js(__("Search error", "woocommerce")); ?></div>').show();
                }
            });
        }
    }

    // Search input handler - triggers all searches when any input changes
    searchFields.on('input', function() {
        const searchTerm = $(this).val().trim();
        clearTimeout(searchTimer);

        // Clear all results containers
        containers.each(function() {
            $(this).find('.woocommerce-ajax-search-results').hide().empty();
        });

        if (!searchTerm) return;

        if (searchTerm.length < woocommerce_ajax_search_params.min_characters) {
            containers.each(function() {
                const resultsContainer = $(this).find('.woocommerce-ajax-search-results');
                resultsContainer.html(`<div class="no-results">${woocommerce_ajax_search_params.min_chars_message}</div>`).show();
            });
            return;
        }

        searchTimer = setTimeout(() => {
            // Perform all searches
            containers.each(function() {
                const container = $(this);
                const resultsContainer = container.find('.woocommerce-ajax-search-results');
                const form = container.find('.woocommerce-ajax-search-form');

                let action = 'woocommerce_ajax_search'; // Default original search

                if (form.hasClass('database-ajax')) {
                    action = 'woocommerce_ajax_database_search';
                } else if (form.hasClass('xml-ajax')) {
                    action = 'woocommerce_ajax_xml_search';
                } else if (form.hasClass('xml-local-ajax')) {
                    action = 'woocommerce_ajax_xml_local_search';
                }

                performSearch(searchTerm, action, resultsContainer);
            });
        }, 300);
    });

    // Initialize on page load
    //initSearch(); // init local storage search
    initXmlSearch();
    
    // Handle result clicks
    containers.on('click', '.woocommerce-ajax-search-results a', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
    });

    //Handle the search result template
    $('.woocommerce-ajax-search-form').on('submit', function(e) {
        if ($(this).find('.woocommerce-ajax-search-results:visible').length > 0) {
            e.preventDefault();

            // Add a small delay to allow redirection
            setTimeout(() => {
                window.location.href = $(this).attr('action') + '?' + $(this).serialize();
            }, 100);
        }
    });

    // Clear results on actual submission
    searchFields.on('keydown', function(e) {
        if (e.key === 'Enter') {
            const form = $(this).closest('.woocommerce-ajax-search-form');
            const resultsContainer = form.find('.woocommerce-ajax-search-results');
            if (resultsContainer.is(':visible')) {
                resultsContainer.hide().empty();
            }
        }
    });
});
