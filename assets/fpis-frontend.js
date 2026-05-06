/**
 * Fanpage Insight Sync - Frontend Application
 * Architecture: Class-based / State-driven
 */

class FPISApp {
    constructor() {
        this.$container = jQuery('#fpis-app-root');
        if (!this.$container.length) return;

        this.state = {
            pages: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            isLoading: false,
            filters: {
                search: '',
                dominant: '',
                region: '',
                platform: '',
                minFollow: '',
                maxFollow: ''
            }
        };

        this.init();
    }

    init() {
        this.bindEvents();
        this.fetchData();
    }

    bindEvents() {
        // Filter inputs
        this.$container.on('change keyup', '.fpis-field', (e) => {
            const $field = jQuery(e.currentTarget);
            const key = $field.data('filter');
            if (key) {
                this.state.filters[key] = $field.val();
                this.state.currentPage = 1;
                this.fetchData();
            }
        });

        // Pagination
        this.$container.on('click', '.fpis-page-btn', (e) => {
            this.state.currentPage = parseInt(jQuery(e.currentTarget).data('page'));
            this.fetchData();
            window.scrollTo({ top: this.$container.offset().top - 100, behavior: 'smooth' });
        });

        // Modal triggers
        this.$container.on('click', '.fpis-card, .fpis-view-btn', (e) => {
            const id = jQuery(e.currentTarget).closest('.fpis-card').data('id');
            if (id) this.openModal(id);
        });

        // Modal close
        jQuery(document).on('click', '.fpis-modal-close, .fpis-modal-backdrop', () => this.closeModal());
        jQuery(window).on('keyup', (e) => { if (e.key === 'Escape') this.closeModal(); });
    }

    async fetchData() {
        if (this.state.isLoading) return;
        this.state.isLoading = true;
        this.renderLoading();

        try {
            const response = await jQuery.ajax({
                url: fpisData.rest_url + '/pages',
                data: {
                    ...this.state.filters,
                    page: this.state.currentPage,
                    per_page: fpisData.per_page || 20
                },
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce)
            });

            this.state.pages = response.data;
            this.state.total = response.total;
            this.state.totalPages = response.pages;
            this.render();
        } catch (error) {
            console.error('FPIS Fetch Error:', error);
            this.renderError();
        } finally {
            this.state.isLoading = false;
        }
    }

    renderLoading() {
        const $grid = this.$container.find('.fpis-asset-grid');
        if ($grid.length) {
            $grid.css('opacity', '0.5');
        }
    }

    renderError() {
        this.$container.find('.fpis-asset-grid').html('<div class="fpis-error">Có lỗi xảy ra khi tải dữ liệu. Vui lòng thử lại.</div>');
    }

    render() {
        const $grid = this.$container.find('.fpis-asset-grid');
        const $pagination = this.$container.find('#fpis-pagination');
        const $count = this.$container.find('.fpis-results-count');

        // Update count
        $count.text(`Hiển thị ${this.state.pages.length} / ${this.state.total} tài sản`);

        // Render Grid
        if (this.state.pages.length === 0) {
            $grid.html('<div class="fpis-no-results">Không tìm thấy tài sản phù hợp.</div>');
        } else {
            const cardsHtml = this.state.pages.map(page => this.templateCard(page)).join('');
            $grid.html(cardsHtml).css('opacity', '1');
        }

        // Render Pagination
        this.renderPagination($pagination);
    }

    templateCard(page) {
        const platform = (page.platform || 'facebook').toLowerCase();
        const followers = parseInt(page.follow_count || 0).toLocaleString('vi-VN');
        const price = page.calculated_price ? parseInt(page.calculated_price).toLocaleString('vi-VN') + ' ₫' : 'Liên hệ';
        
        return `
            <div class="fpis-card fpis-animate-in" data-id="${page.id}">
                <div class="fpis-card-header">
                    <span class="fpis-badge fpis-badge-${platform === 'facebook' ? 'fb' : 'tt'}">${page.platform || 'FB'}</span>
                    <span class="fpis-verified-badge"><span class="dashicons dashicons-shield-alt" style="color:var(--fpis-status-success)"></span> Verified</span>
                </div>
                <h3 class="fpis-asset-title">${page.page_name}</h3>
                <div class="fpis-metric-row">
                    <div class="fpis-metric-item">
                        <span class="label">Followers</span>
                        <span class="value">${followers}</span>
                    </div>
                    <div class="fpis-metric-item">
                        <span class="label">Khu vực</span>
                        <span class="value">${page.region_focus || 'Toàn quốc'}</span>
                    </div>
                </div>
                <div class="fpis-card-footer">
                    <div class="fpis-price-display">
                        <span class="label">Giá ước tính</span>
                        <span class="price">${price}</span>
                    </div>
                    <button class="fpis-view-btn">Chi tiết Audit</button>
                </div>
            </div>
        `;
    }

    renderPagination($el) {
        if (this.state.totalPages <= 1) {
            $el.html('');
            return;
        }

        let html = '<div class="fpis-pagination-wrap">';
        const current = this.state.currentPage;
        const total = this.state.totalPages;
        
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                html += `<button class="fpis-page-btn ${i === current ? 'active' : ''}" data-page="${i}">${i}</button>`;
            } else if (i === current - 2 || i === current + 2) {
                html += '<span class="fpis-page-dots">...</span>';
            }
        }
        html += '</div>';
        $el.html(html);
    }

    async openModal(id) {
        const $modal = jQuery('.fpis-modal-system');
        const $content = $modal.find('.fpis-modal-placeholder');
        
        $modal.addClass('active').fadeIn(200);
        $content.html('<div class="fpis-modal-loading">Đang phân tích dữ liệu chuyên sâu...</div>');

        try {
            const data = await jQuery.ajax({
                url: fpisData.rest_url + '/pages/' + id,
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce)
            });
            this.renderModal(data, $content);
        } catch (error) {
            $content.html('<div class="fpis-error">Không thể tải thông tin chi tiết.</div>');
        }
    }

    renderModal(data, $el) {
        const female = parseInt(data.female_pct || 50);
        const male = 100 - female;
        const suitable = data.suitable_json ? JSON.parse(data.suitable_json) : [];
        const unsuitable = data.unsuitable_json ? JSON.parse(data.unsuitable_json) : [];

        const html = `
            <div class="fpis-modal-header">
                <div class="fpis-header-left">
                    <div class="fpis-audit-id">AUDIT REPORT #${data.id}</div>
                    <h1 class="fpis-modal-title">${data.page_name}</h1>
                    <div class="fpis-modal-meta">
                        <span class="fpis-badge fpis-badge-fb">${data.platform}</span>
                        <span class="fpis-sync-time">Cập nhật: ${data.synced_at || 'Vừa xong'}</span>
                    </div>
                </div>
                <button class="fpis-modal-close"><span class="dashicons dashicons-no-alt"></span> Đóng</button>
            </div>
            <div class="fpis-modal-body">
                <div class="fpis-report-grid">
                    <div class="fpis-report-section fpis-section-full fpis-stats-summary">
                        <div class="fpis-stat-box">
                            <label>Followers</label>
                            <div class="fpis-val">${parseInt(data.follow_count).toLocaleString('vi-VN')}</div>
                        </div>
                        <div class="fpis-stat-box">
                            <label>Giá ước tính</label>
                            <div class="fpis-val primary">${data.calculated_price ? parseInt(data.calculated_price).toLocaleString('vi-VN') + ' ₫' : 'Liên hệ'}</div>
                        </div>
                        <div class="fpis-stat-box">
                            <label>Khu vực</label>
                            <div class="fpis-val">${data.region_focus || 'Toàn quốc'}</div>
                        </div>
                    </div>

                    <div class="fpis-report-section fpis-demographics">
                        <h3 class="fpis-section-title">Nhân khẩu học</h3>
                        <div class="fpis-gender-wrapper">
                            <div class="fpis-gender-flex">
                                <span>Nữ ${female}%</span>
                                <span>Nam ${male}%</span>
                            </div>
                            <div class="fpis-gender-bar-full">
                                <div class="fpis-female-fill" style="width: ${female}%"></div>
                                <div class="fpis-male-fill" style="width: ${male}%"></div>
                            </div>
                        </div>
                        <div style="margin-top:32px">
                            <label class="fpis-filter-label">Độ tuổi chủ đạo</label>
                            <div style="font-size: 2.5rem; font-weight: 900; color: var(--fpis-brand-primary)">${data.age_top_group || '18-24'}</div>
                        </div>
                    </div>

                    <div class="fpis-report-section fpis-ai-report">
                        <h3 class="fpis-section-title">Đánh giá chuyên sâu (AI)</h3>
                        <div class="fpis-description-text">
                            ${data.description || 'Hệ thống đang phân tích hành vi người dùng...'}
                        </div>
                    </div>

                    <div class="fpis-report-section fpis-section-full fpis-brand-fit">
                        <h3 class="fpis-section-title">Phù hợp ngành hàng</h3>
                        <div class="fpis-report-grid" style="gap:20px">
                            <div style="grid-column: span 6">
                                <label class="fpis-filter-label">Nên hợp tác</label>
                                <div class="fpis-tag-wrap">
                                    ${suitable.map(t => `<span class="fpis-tag-item fpis-tag-pos">${t}</span>`).join('')}
                                </div>
                            </div>
                            <div style="grid-column: span 6">
                                <label class="fpis-filter-label">Hạn chế</label>
                                <div class="fpis-tag-wrap">
                                    ${unsuitable.map(t => `<span class="fpis-tag-item fpis-tag-neg">${t}</span>`).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="fpis-modal-footer" style="padding: 30px 60px; background: white; border-top: 1px solid var(--fpis-border); display: flex; justify-content: flex-end; gap: 20px;">
                <a href="${data.link_fanpage}" target="_blank" class="fpis-view-btn" style="background:var(--fpis-bg-main); color:var(--fpis-text-primary) !important">Xem Fanpage</a>
                <a href="${fpisData.zalo_url}" target="_blank" class="fpis-view-btn">Liên hệ sở hữu ngay</a>
            </div>
        `;
        $el.html(html);
    }

    closeModal() {
        const $modal = jQuery('.fpis-modal-system');
        $modal.removeClass('active').fadeOut(300);
    }
}

jQuery(document).ready(() => {
    window.fpisApp = new FPISApp();
});
