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

        $.ajax({
            url: fpisData.rest_url + '/pages',
            data: {
                search: search,
                dominant: dominant,
                region: region,
                page: currentPage,
                per_page: perPage
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce);
                $results.html('<tr><td colspan="7" style="text-align:center;">Đang tải...</td></tr>');
            },
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.total, response.pages);
            }
        });
    }

    function renderTable(data) {
        if (!data || data.length === 0) {
            $results.html('<tr><td colspan="7" style="text-align:center;">Không tìm thấy kết quả nào.</td></tr>');
            return;
        }

        let html = '';
        data.forEach(row => {
            html += `<tr data-id="${row.id}">
                <td><strong>${row.page_name}</strong></td>
                <td>${parseInt(row.follow_count).toLocaleString('vi-VN')}</td>
                <td><span class="fpis-badge">${row.region_focus || 'N/A'}</span></td>
                ${fpisData.is_logged_in ? `
                    <td>${row.female_pct ? row.female_pct + '%' : 'N/A'}</td>
                    <td>${row.age_top_group || 'N/A'}</td>
                    <td>${row.tinh_trang || 'N/A'}</td>
                ` : ''}
                <td><button class="fpis-btn fpis-view-btn" data-id="${row.id}">Chi tiết</button></td>
            </tr>`;
        });
        $results.html(html);
    }

    function renderPagination(total, pages) {
        if (pages <= 1) {
            $pagination.html('');
            return;
        }

        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += `<button class="fpis-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        $pagination.html(html);
    }

    // Event handlers
    $('#fpis-search, #fpis-gender, #fpis-region').on('change keyup', function() {
        currentPage = 1;
        fetchPages();
    });

    $(document).on('click', '.fpis-page-btn', function() {
        currentPage = parseInt($(this).data('page'));
        fetchPages();
    });

    $(document).on('click', '.fpis-view-btn, #fpis-table tr', function(e) {
        const id = $(this).data('id') || $(this).closest('tr').data('id');
        if (id) {
            openModal(id);
        }
    });

    function openModal(id) {
        $.ajax({
            url: fpisData.rest_url + '/pages/' + id,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fpisData.nonce);
                $modal.show();
                $modalBody.html('<p>Đang tải chi tiết...</p>');
            },
            success: function(row) {
                let html = `
                    <div class="fpis-modal-header">
                        <h2>${row.page_name}</h2>
                        <a href="${row.link_fanpage}" target="_blank" class="fpis-link">Xem Fanpage <span class="dashicons dashicons-external"></span></a>
                    </div>
                    <div class="fpis-modal-grid">
                        <div class="fpis-stat-card">
                            <label>Followers</label>
                            <div class="fpis-value">${parseInt(row.follow_count).toLocaleString('vi-VN')}</div>
                        </div>
                        <div class="fpis-stat-card">
                            <label>Khu vực</label>
                            <div class="fpis-value">${row.region_focus || 'Nationwide'}</div>
                        </div>
                    </div>
                    <div class="fpis-modal-section">
                        <h3>Mô tả AI</h3>
                        <p>${row.description || 'Chưa có dữ liệu phân tích AI.'}</p>
                    </div>
                    <div class="fpis-modal-footer">
                        <a href="${fpisData.zalo_url}" target="_blank" class="fpis-btn fpis-zalo-btn">Liên hệ Zalo</a>
                    </div>
                `;
                $modalBody.html(html);
            }
        });
    }

    $('.fpis-modal-close').on('click', function() {
        $modal.hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            $modal.hide();
        }
    });

    // Initial fetch
    fetchPages();
});
