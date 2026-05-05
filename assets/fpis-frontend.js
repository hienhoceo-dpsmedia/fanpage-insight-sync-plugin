jQuery(document).ready(function($) {
    const $results = $('#fpis-results');
    const $pagination = $('#fpis-pagination');
    const $modal = $('#fpis-modal');
    const $modalBody = $('#fpis-modal-body');
    const perPage = $('#fpis-app').data('per-page') || 20;
    
    let currentPage = 1;

    function fetchPages() {
        const search = $('#fpis-search').val();
        const dominant = $('#fpis-gender').val();
        const region = $('#fpis-region').val();
        const platform = $('#fpis-platform').val();
        const minFollow = $('#fpis-min-follow').val();
        const maxFollow = $('#fpis-max-follow').val();

        $.ajax({
            url: fpisData.rest_url + '/pages',
            data: {
                search: search,
                dominant: dominant,
                region: region,
                platform: platform,
                follow_min: minFollow,
                follow_max: maxFollow,
                page: currentPage,
                per_page: perPage
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce);
                $results.html('<div class="fpis-loading">Đang tải danh sách tài sản...</div>');
            },
            success: function(response) {
                renderGrid(response.data);
                renderPagination(response.total, response.pages);
            }
        });
    }

    function renderGrid(data) {
        if (!data || data.length === 0) {
            $results.html('<div class="fpis-no-results">Không tìm thấy tài sản phù hợp.</div>');
            return;
        }

        let html = '';
        data.forEach(row => {
            const platformClass = (row.platform || 'facebook').toLowerCase();
            const followCount = parseInt(row.follow_count || 0).toLocaleString('vi-VN');
            const price = row.calculated_price ? parseInt(row.calculated_price).toLocaleString('vi-VN') + ' ₫' : 'Liên hệ';
            
            html += `
                <div class="fpis-card" data-id="${row.id}">
                    <div class="fpis-card-header">
                        <span class="fpis-platform-tag fpis-platform-${platformClass}">${row.platform || 'FB'}</span>
                        <span class="fpis-verified-badge"><span class="dashicons dashicons-shield-alt"></span> Verified</span>
                    </div>
                    <div class="fpis-card-body">
                        <h3 class="fpis-asset-name">${row.page_name}</h3>
                        <div class="fpis-asset-metrics">
                            <div class="fpis-metric">
                                <span class="fpis-label">Followers</span>
                                <span class="fpis-value">${followCount}</span>
                            </div>
                            <div class="fpis-metric">
                                <span class="fpis-label">Khu vực</span>
                                <span class="fpis-value">${row.region_focus || 'Toàn quốc'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="fpis-card-footer">
                        <div class="fpis-price-box">
                            <span class="fpis-label">Giá ước tính</span>
                            <span class="fpis-price">${price}</span>
                        </div>
                        <button class="fpis-audit-btn">Xem Audit</button>
                    </div>
                </div>
            `;
        });
        $results.html(html);
    }

    function renderPagination(total, pages) {
        if (pages <= 1) {
            $pagination.html('');
            return;
        }

        let html = '<div class="fpis-pagination">';
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(pages, currentPage + 2);

        if (start > 1) html += `<button class="fpis-page-btn" data-page="1">1</button>${start > 2 ? '<span>...</span>' : ''}`;
        
        for (let i = start; i <= end; i++) {
            html += `<button class="fpis-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (end < pages) html += `${end < pages - 1 ? '<span>...</span>' : ''}<button class="fpis-page-btn" data-page="${pages}">${pages}</button>`;
        
        html += '</div>';
        $pagination.html(html);
    }

    // Event handlers
    $('#fpis-search, #fpis-gender, #fpis-region, #fpis-platform, #fpis-min-follow, #fpis-max-follow').on('change keyup', function() {
        currentPage = 1;
        fetchPages();
    });

    $(document).on('click', '.fpis-page-btn', function() {
        currentPage = parseInt($(this).data('page'));
        fetchPages();
    });

    $(document).on('click', '.fpis-card, .fpis-audit-btn', function(e) {
        const id = $(this).closest('.fpis-card').data('id');
        if (id) {
            openModal(id);
        }
    });

    function openModal(id) {
        $.ajax({
            url: fpisData.rest_url + '/pages/' + id,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce);
                $modal.css('display', 'flex').hide().fadeIn(200);
                $modalBody.html('<div class="fpis-modal-loading">Đang tạo báo cáo kiểm định...</div>');
            },
            success: function(row) {
                const femalePct = row.female_pct || 0;
                const malePct = 100 - femalePct;
                const suitable = row.suitable_json ? JSON.parse(row.suitable_json) : [];
                const unsuitable = row.unsuitable_json ? JSON.parse(row.unsuitable_json) : [];
                
                let html = `
                    <div class="fpis-audit-header">
                        <div class="fpis-audit-meta">
                            <span class="fpis-audit-id">#ASSET-${row.id}</span>
                            <span class="fpis-platform-tag fpis-platform-${row.platform}">${row.platform}</span>
                            <span class="fpis-audit-status status-verified">Verified</span>
                        </div>
                        <h1>${row.page_name}</h1>
                        <p class="fpis-sync-time">Cập nhật cuối: ${row.synced_at || 'Vừa xong'}</p>
                    </div>

                    <div class="fpis-audit-grid">
                        <!-- Key Stats -->
                        <div class="fpis-audit-section fpis-stats-summary">
                            <div class="fpis-stat-box">
                                <label>Followers</label>
                                <div class="fpis-val">${parseInt(row.follow_count).toLocaleString('vi-VN')}</div>
                            </div>
                            <div class="fpis-stat-box">
                                <label>Giá ước tính</label>
                                <div class="fpis-val primary">${row.calculated_price ? parseInt(row.calculated_price).toLocaleString('vi-VN') + ' ₫' : 'Liên hệ'}</div>
                            </div>
                            <div class="fpis-stat-box">
                                <label>Vùng tiếp cận</label>
                                <div class="fpis-val">${row.region_focus || 'Toàn quốc'}</div>
                            </div>
                        </div>

                        <!-- Demographics -->
                        <div class="fpis-audit-section fpis-demographics">
                            <h3>Phân bổ giới tính</h3>
                            <div class="fpis-gender-bar">
                                <div class="fpis-bar-female" style="width: ${femalePct}%"><span>Nữ ${femalePct}%</span></div>
                                <div class="fpis-bar-male" style="width: ${malePct}%"><span>Nam ${malePct}%</span></div>
                            </div>
                            
                            <h3 style="margin-top:20px">Độ tuổi chủ đạo</h3>
                            <div class="fpis-age-focus">${row.age_top_group || '18-24'}</div>
                        </div>

                        <!-- AI Analysis -->
                        <div class="fpis-audit-section fpis-ai-description">
                            <h3>Báo cáo đánh giá của AI</h3>
                            <div class="fpis-description-text">
                                ${row.description || 'Hệ thống đang phân tích chuyên sâu về nội dung và hành vi người dùng của tài sản này.'}
                            </div>
                        </div>

                        <!-- Brand Fit -->
                        <div class="fpis-audit-section fpis-brand-fit">
                            <h3>Phù hợp ngành hàng</h3>
                            <div class="fpis-tag-cloud">
                                ${suitable.map(tag => `<span class="fpis-tag tag-positive">${tag}</span>`).join('')}
                            </div>
                            <h3 style="margin-top:20px">Hạn chế</h3>
                            <div class="fpis-tag-cloud">
                                ${unsuitable.map(tag => `<span class="fpis-tag tag-negative">${tag}</span>`).join('')}
                            </div>
                        </div>
                    </div>

                    <div class="fpis-audit-footer">
                        <div class="fpis-footer-info">
                            <p>Tài sản này đã qua quy trình kiểm định 3 bước của Asset Exchange.</p>
                        </div>
                        <div class="fpis-footer-actions">
                            <a href="${row.link_fanpage}" target="_blank" class="fpis-btn-secondary">Xem trực tiếp</a>
                            <a href="${fpisData.zalo_url}" target="_blank" class="fpis-btn-primary">Liên hệ sở hữu ngay</a>
                        </div>
                    </div>
                `;
                $modalBody.html(html);
            }
        });
    }

    $('.fpis-modal-close, .fpis-modal-overlay').on('click', function() {
        $modal.fadeOut(200);
    });

    $(window).on('keyup', function(e) {
        if (e.key === "Escape") $modal.fadeOut(200);
    });

    // Initial fetch
    fetchPages();
});
