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

        // Handle analyze button click
        $analyzeButton.on('click', function() {
            performAnalysis('analyze');
        });

        // Handle tokenize button click
        $tokenizeButton.on('click', function() {
            performAnalysis('tokenize');
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
    });

})(jQuery);