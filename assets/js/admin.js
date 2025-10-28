// OTM admin scripts

jQuery(document).ready(function($) {
    'use strict';
    
    // Dashboard enhancements
    if ($('.otm-dashboard-wrap').length) {
        initDashboard();
    }
    // AJAX moderation for submissions
    $(document).on('submit', '.otm-action-form', function(e) {
        var $form = $(this);
        if (!$form.closest('body').hasClass('wp-admin')) return; // only enhance in admin
        e.preventDefault();
        var data = $form.serializeArray();
        data.push({name: 'action', value: 'otm_update_submission'});
        var $btn = $form.find('input[type=submit]');
        $btn.prop('disabled', true);
        $.post(ajaxurl, $.param(data))
            .done(function(resp){
                if (resp && resp.success) {
                    // update row UI
                    var $row = $form.closest('tr');
                    $row.find('.otm-status-badge').text(resp.data.status.charAt(0).toUpperCase() + resp.data.status.slice(1));
                    $row.find('.otm-points-input').val(resp.data.points);
                } else if (resp && resp.data && resp.data.message) {
                    alert(resp.data.message);
                }
            })
            .fail(function(){ alert('Request failed'); })
            .always(function(){ $btn.prop('disabled', false); });
    });
    
    function initDashboard() {
        // Add loading states to buttons
        $('.otm-btn').on('click', function() {
            var $btn = $(this);
            if (!$btn.hasClass('otm-btn-outline')) {
                $btn.addClass('otm-loading');
                setTimeout(function() {
                    $btn.removeClass('otm-loading');
                }, 1000);
            }
        });
        
        // Animate stat numbers on load
        animateStatNumbers();
        
        // Add hover effects to cards
        addCardHoverEffects();
        
        // Initialize simple charts if data exists
        initSimpleCharts();
    }
    
    function animateStatNumbers() {
        $('.otm-stat-number').each(function() {
            var $this = $(this);
            var countTo = parseInt($this.text());
            
            $({ countNum: 0 }).animate({
                countNum: countTo
            }, {
                duration: 1500,
                easing: 'swing',
                step: function() {
                    $this.text(Math.floor(this.countNum));
                },
                complete: function() {
                    $this.text(this.countNum);
                }
            });
        });
    }
    
    function addCardHoverEffects() {
        $('.otm-stat-card').hover(
            function() {
                $(this).find('.otm-stat-icon').css('transform', 'scale(1.1)');
            },
            function() {
                $(this).find('.otm-stat-icon').css('transform', 'scale(1)');
            }
        );
    }
    
    function initSimpleCharts() {
        // Create a simple submission status chart
        var pendingCount = parseInt($('.otm-badge-pending').first().text()) || 0;
        var approvedCount = parseInt($('.otm-badge-approved').first().text()) || 0;
        var rejectedCount = parseInt($('.otm-badge-rejected').first().text()) || 0;
        
        if (pendingCount + approvedCount + rejectedCount > 0) {
            createSubmissionChart(pendingCount, approvedCount, rejectedCount);
        }
    }
    
    function createSubmissionChart(pending, approved, rejected) {
        var total = pending + approved + rejected;
        var pendingPercent = (pending / total) * 100;
        var approvedPercent = (approved / total) * 100;
        var rejectedPercent = (rejected / total) * 100;
        
        // Add chart container after stats grid
        var chartHtml = `
            <div class="otm-chart-container">
                <h3>Submission Status Overview</h3>
                <div class="otm-chart-placeholder">
                    <div class="otm-chart-content">
                        <div class="otm-chart-bars">
                            <div class="otm-chart-bar">
                                <div class="otm-chart-label">Pending</div>
                                <div class="otm-progress-bar">
                                    <div class="otm-progress-fill" style="width: ${pendingPercent}%; background: #fff3cd;"></div>
                                </div>
                                <div class="otm-chart-value">${pending} (${pendingPercent.toFixed(1)}%)</div>
                            </div>
                            <div class="otm-chart-bar">
                                <div class="otm-chart-label">Approved</div>
                                <div class="otm-progress-bar">
                                    <div class="otm-progress-fill" style="width: ${approvedPercent}%; background: #d1edff;"></div>
                                </div>
                                <div class="otm-chart-value">${approved} (${approvedPercent.toFixed(1)}%)</div>
                            </div>
                            <div class="otm-chart-bar">
                                <div class="otm-chart-label">Rejected</div>
                                <div class="otm-progress-bar">
                                    <div class="otm-progress-fill" style="width: ${rejectedPercent}%; background: #f8d7da;"></div>
                                </div>
                                <div class="otm-chart-value">${rejected} (${rejectedPercent.toFixed(1)}%)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('.otm-stats-grid').after(chartHtml);
        
        // Animate the progress bars
        setTimeout(function() {
            $('.otm-progress-fill').each(function() {
                var $this = $(this);
                var width = $this.css('width');
                $this.css('width', '0%').animate({
                    width: width
                }, 1000);
            });
        }, 500);
    }
    
    // Add CSS for chart elements
    var chartCSS = `
        <style>
        .otm-chart-content {
            padding: 20px;
        }
        
        .otm-chart-bars {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .otm-chart-bar {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .otm-chart-label {
            min-width: 80px;
            font-weight: 600;
            color: #374151;
        }
        
        .otm-chart-value {
            min-width: 80px;
            font-size: 14px;
            color: #6b7280;
            text-align: right;
        }
        
        .otm-progress-bar {
            flex: 1;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .otm-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .otm-chart-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .otm-chart-label,
            .otm-chart-value {
                min-width: auto;
                text-align: left;
            }
        }
        </style>
    `;
    
    $('head').append(chartCSS);
});