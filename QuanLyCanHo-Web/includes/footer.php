<?php

declare(strict_types=1);
?>
            </main>

            <footer class="app-footer">
                <div class="footer-container">
                    <span>© <?= date('Y') ?> Hệ Thống Quản Lý Căn Hộ Dịch Vụ — Enterprise Edition</span>
                    <span style="color: #94a3b8;">Phiên bản Quản Trị</span>
                </div>
            </footer>
        </div> <!-- End app-main-wrapper -->
    </div> <!-- End app-layout -->

    <script src="<?= url('/assets/js/module2-lightbox.js') ?>"></script>
    <script>window.PROJECT_ROOT = <?= json_encode(url('/')) ?>;</script>
    <script src="<?= url('/assets/js/address-autocomplete.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isMobile = window.innerWidth <= 992;

        if (sidebar) {
            if (isMobile) {
                const isOpen = sidebar.classList.toggle('show-mobile');
                if (overlay) {
                    overlay.classList.toggle('active', isOpen);
                }
                document.body.style.overflow = isOpen ? 'hidden' : '';
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('show-mobile');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.querySelectorAll('.nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('show-mobile');
                        if (overlay) overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            });
        }
    });

    function toggleUserDropdown(event) {
        event.stopPropagation();
        const menu = document.getElementById('userDropdownMenu');
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    document.addEventListener('click', function(event) {
        const menu = document.getElementById('userDropdownMenu');
        if (menu && menu.style.display === 'block') {
            if (!event.target.closest('.user-dropdown-container')) {
                menu.style.display = 'none';
            }
        }
    });

    // Tự động định dạng dấu chấm phân cách hàng nghìn (ví dụ: 100.000, 6.000.000)
    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('currency-mask')) {
            const raw = e.target.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            if (raw === '') {
                e.target.value = '';
            } else {
                e.target.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        }
    });

    function initCurrencyMasks() {
        document.querySelectorAll('.currency-mask').forEach(function(input) {
            if (input.value && input.value !== '') {
                const raw = input.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
                if (raw !== '') {
                    input.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCurrencyMasks);
    } else {
        initCurrencyMasks();
    }
    </script>
</body>
</html>
