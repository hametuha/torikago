/**
 * Admin interface JavaScript for Kagome Analyzer
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const $analyzeButton = $('#analyze_button');
        const $tokenizeButton = $('#tokenize_button');
        const $testText = $('#test_text');
        const $analysisMode = $('#analysis_mode');
        const $resultsDiv = $('#analysis_results');
        const $resultsOutput = $('#results_output');
        const $loadingDiv = $('#analysis_loading');
        
        // Performance test elements
        const $performanceButton = $('#run_performance_test');
        const $performanceSearchTerm = $('#performance_search_term');
        const $performanceLimit = $('#performance_limit');
        const $performanceResults = $('#performance_results');
        const $performanceOutput = $('#performance_output');
        const $performanceLoading = $('#performance_loading');

        // Handle analyze button click
        $analyzeButton.on('click', function() {
            performAnalysis('analyze');
        });

        // Handle tokenize button click
        $tokenizeButton.on('click', function() {
            performAnalysis('tokenize');
        });

        // Handle performance test button click
        $performanceButton.on('click', function(e) {
            e.preventDefault();
            performPerformanceTest();
        });

        /**
         * Perform text analysis
         * @param {string} action - 'analyze' or 'tokenize'
         */
        function performAnalysis(action) {
            const text = $testText.val().trim();
            if (!text) {
                alert(wpKagomeAdmin.strings.noText);
                return;
            }

            // Show loading
            $loadingDiv.show();
            $resultsDiv.hide();
            $analyzeButton.prop('disabled', true);
            $tokenizeButton.prop('disabled', true);

            const endpoint = wpKagomeAdmin.apiUrl + action;
            const options = {
                mode: $analysisMode.val()
            };

            // Make REST API request
            wp.apiFetch({
                path: endpoint,
                method: 'POST',
                data: {
                    text: text,
                    options: options
                }
            }).then(function(response) {
                displayResults(response, action);
            }).catch(function(error) {
                console.error('Analysis error:', error);
                let errorMessage = wpKagomeAdmin.strings.analysisError;
                if (error.message) {
                    errorMessage += ': ' + error.message;
                }
                $resultsOutput.text(errorMessage);
                $resultsDiv.show();
            }).finally(function() {
                // Hide loading
                $loadingDiv.hide();
                $analyzeButton.prop('disabled', false);
                $tokenizeButton.prop('disabled', false);
            });
        }

        /**
         * Display analysis results
         * @param {Object} response - API response
         * @param {string} action - 'analyze' or 'tokenize'
         */
        function displayResults(response, action) {
            let output;

            if (action === 'tokenize') {
                // Simple tokenized output
                const words = response.tokens.map(token => token.surface || '').join(' ');
                output = 'Tokenized: ' + words + '\n\n';
                
                // Detailed token information
                output += 'Detailed tokens:\n';
                response.tokens.forEach((token, index) => {
                    output += `${index + 1}. ${token.surface} (${token.pos || 'Unknown'})\n`;
                });
            } else {
                // Full analysis output
                output = 'Analysis Results:\n';
                output += '=================\n\n';
                
                if (response.tokens && response.tokens.length > 0) {
                    response.tokens.forEach((token, index) => {
                        output += `${index + 1}. Surface: ${token.surface}\n`;
                        if (token.features && token.features.length > 0) {
                            output += `   Features: ${token.features.join(', ')}\n`;
                        }
                        if (token.pos) {
                            output += `   Part of Speech: ${token.pos}\n`;
                        }
                        output += '\n';
                    });
                } else {
                    output += 'No tokens found.\n';
                }
            }

            // Add options info
            if (response.options && Object.keys(response.options).length > 0) {
                output += '\nOptions used:\n';
                output += '=============\n';
                for (const [key, value] of Object.entries(response.options)) {
                    if (value !== null && value !== undefined && value !== '') {
                        output += `${key}: ${value}\n`;
                    }
                }
            }

            $resultsOutput.text(output);
            $resultsDiv.show();
        }

        // Auto-resize textareas
        $('textarea').each(function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        $('textarea').on('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        /**
         * Perform performance test
         */
        function performPerformanceTest() {
            const searchTerm = $performanceSearchTerm.val().trim();
            const limit = parseInt($performanceLimit.val()) || 50;

            if (!searchTerm) {
                alert(wpKagomeAdmin.strings.noText);
                return;
            }

            // Show loading state
            $performanceButton.prop('disabled', true).text(wpKagomeAdmin.strings.analyzing);
            $performanceLoading.show();
            $performanceResults.hide();

            // Make API request
            wp.apiFetch({
                path: wpKagomeAdmin.apiUrl + 'performance',
                method: 'POST',
                data: {
                    search: searchTerm,
                    limit: limit
                }
            }).then(function(response) {
                displayPerformanceResults(response);
            }).catch(function(error) {
                console.error('Performance test failed:', error);
                let errorMessage = wpKagomeAdmin.strings.analysisError;
                if (error.message) {
                    errorMessage += ': ' + error.message;
                }
                alert(errorMessage);
            }).finally(function() {
                // Reset button state
                $performanceButton.prop('disabled', false).text('Run Performance Test');
                $performanceLoading.hide();
            });
        }

        /**
         * Display performance test results
         * @param {Object} data - Performance test results
         */
        function displayPerformanceResults(data) {
            const kagomeTest = data.tests.kagome_optimized;
            const wpTest = data.tests.wordpress_default;

            let html = '<div class="performance-comparison">';

            // Summary section
            html += '<div class="performance-summary" style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">';
            html += '<h4>Search Performance Comparison</h4>';
            html += '<p><strong>Search Term:</strong> ' + escapeHtml(data.search_term) + '</p>';
            html += '<p><strong>Result Limit:</strong> ' + data.limit + '</p>';

            if (data.improvement) {
                const speedup = data.improvement.speed_ratio;
                const percentage = data.improvement.percentage;
                const timeSaved = data.improvement.time_saved_ms;

                html += '<p style="font-size: 16px; font-weight: bold; color: #0073aa;">';
                html += 'Kagome is <span style="color: #d63638;">' + speedup + 'x faster</span> ';
                html += '(' + percentage + '% improvement, ' + timeSaved + 'ms saved)';
                html += '</p>';
            }
            html += '</div>';

            // Results table
            html += '<table class="widefat" style="margin-bottom: 20px;">';
            html += '<thead><tr>';
            html += '<th>Method</th>';
            html += '<th>Execution Time</th>';
            html += '<th>Memory Used</th>';
            html += '<th>Found Posts</th>';
            html += '<th>Returned Posts</th>';
            html += '<th>DB Queries</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            // Kagome optimized row
            html += '<tr style="background: #e8f5e8;">';
            html += '<td><strong>' + escapeHtml(kagomeTest.method) + '</strong></td>';
            html += '<td>' + kagomeTest.execution_time_ms + ' ms</td>';
            html += '<td>' + kagomeTest.memory_used_kb + ' KB</td>';
            html += '<td>' + kagomeTest.found_posts + '</td>';
            html += '<td>' + kagomeTest.returned_posts + '</td>';
            html += '<td>' + kagomeTest.db_queries + '</td>';
            html += '</tr>';

            // WordPress default row
            html += '<tr style="background: #fff2cc;">';
            html += '<td><strong>' + escapeHtml(wpTest.method) + '</strong></td>';
            html += '<td>' + wpTest.execution_time_ms + ' ms</td>';
            html += '<td>' + wpTest.memory_used_kb + ' KB</td>';
            html += '<td>' + wpTest.found_posts + '</td>';
            html += '<td>' + wpTest.returned_posts + '</td>';
            html += '<td>' + wpTest.db_queries + '</td>';
            html += '</tr>';

            html += '</tbody></table>';

            // Raw data details
            html += '<details style="margin-top: 15px;">';
            html += '<summary>Raw Response Data</summary>';
            html += '<pre style="background: #f6f7f7; padding: 10px; margin-top: 10px; overflow: auto; max-height: 300px;">';
            html += JSON.stringify(data, null, 2);
            html += '</pre>';
            html += '</details>';

            html += '</div>';

            $performanceOutput.html(html);
            $performanceResults.show();
        }

        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} - Escaped text
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });

})(jQuery);