/**
 * Google Maps Style Address Autocomplete for QuanLyCanHo-Web
 * Supports instant suggestions, keyboard navigation, body portal, and 1-click select.
 */
(function() {
    'use strict';

    // Base API URL helper with safe multi-level fallback
    function getApiUrl(query) {
        let root = window.PROJECT_ROOT;
        if (!root || root === '/') {
            const path = window.location.pathname;
            const idx = path.indexOf('/QuanLyCanHo-Web');
            root = idx !== -1 ? '/QuanLyCanHo-Web' : '';
        }
        root = root.replace(/\/+$/, '');
        return `${root}/api/address-suggest.php?q=${encodeURIComponent(query || '')}`;
    }

    function initAddressAutocomplete(input) {
        if (!input || input.tagName !== 'INPUT' || input.dataset.noAutocomplete === 'true' || input.dataset.autocompleteBound) return;
        input.dataset.autocompleteBound = 'true';
        input.setAttribute('autocomplete', 'off');

        // Create dropdown container attached to document.body (Body Portal pattern)
        // Prevents clipping by parent cards, modals, or overflow:hidden
        const dropdown = document.createElement('div');
        dropdown.className = 'address-autocomplete-dropdown';
        dropdown.style.display = 'none';
        document.body.appendChild(dropdown);

        let debounceTimer = null;
        let activeIndex = -1;
        let currentItems = [];
        let isDropdownOpen = false;

        function updatePosition() {
            if (!isDropdownOpen || dropdown.style.display === 'none') return;
            const rect = input.getBoundingClientRect();

            // If input is detached or invisible, hide dropdown
            if (rect.width === 0 && rect.height === 0) {
                hideDropdown();
                return;
            }

            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            const spaceBelow = viewportHeight - rect.bottom;
            const spaceAbove = rect.top;

            const dropdownWidth = Math.max(rect.width, 320);
            let left = rect.left;
            if (left + dropdownWidth > viewportWidth - 16) {
                left = Math.max(12, viewportWidth - dropdownWidth - 16);
            }

            dropdown.style.position = 'fixed';
            dropdown.style.left = `${Math.round(left)}px`;
            dropdown.style.width = `${Math.round(dropdownWidth)}px`;
            dropdown.style.zIndex = '99999999';

            // Auto-flip upwards if tight space below (< 240px) and more space above
            if (spaceBelow < 240 && spaceAbove > spaceBelow) {
                const maxHeight = Math.min(380, Math.max(160, spaceAbove - 20));
                dropdown.style.top = 'auto';
                dropdown.style.bottom = `${Math.round(viewportHeight - rect.top + 6)}px`;
                dropdown.style.maxHeight = `${maxHeight}px`;
            } else {
                const maxHeight = Math.min(380, Math.max(160, spaceBelow - 20));
                dropdown.style.bottom = 'auto';
                dropdown.style.top = `${Math.round(rect.bottom + 6)}px`;
                dropdown.style.maxHeight = `${maxHeight}px`;
            }
        }

        function renderSuggestions(items) {
            currentItems = items || [];
            activeIndex = -1;
            dropdown.innerHTML = '';

            if (currentItems.length === 0) {
                dropdown.innerHTML = `
                    <div class="address-autocomplete-empty">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" style="margin-bottom: 6px; display: block; margin-left: auto; margin-right: auto;">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.3-4.3"/>
                        </svg>
                        <span>Không tìm thấy gợi ý phù hợp. Bạn có thể gõ trực tiếp địa chỉ.</span>
                    </div>
                `;
                isDropdownOpen = true;
                dropdown.style.display = 'block';
                updatePosition();
                return;
            }

            const header = document.createElement('div');
            header.className = 'address-autocomplete-header';
            header.innerHTML = `
                <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; color: #1e40af;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    ĐỊA CHỈ VIỆT NAM (GOOGLE MAPS)
                </span>
                <span style="font-size: 0.72rem; color: #64748b; font-weight: 500;">Bấm vào địa chỉ để chọn</span>
            `;
            dropdown.appendChild(header);

            currentItems.forEach((item, idx) => {
                const row = document.createElement('div');
                row.className = 'address-autocomplete-item';
                row.dataset.index = String(idx);
                row.innerHTML = `
                    <div class="address-item-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ef4444" stroke="#b91c1c" stroke-width="1.5">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            <circle cx="12" cy="9" r="2.5" fill="#ffffff"/>
                        </svg>
                    </div>
                    <div class="address-item-content">
                        <div class="address-item-main">${escapeHtml(item.main_text)}</div>
                        <div class="address-item-sub">${escapeHtml(item.secondary_text)}</div>
                    </div>
                    <div class="address-item-action">
                        <span class="select-badge">Chọn</span>
                    </div>
                `;
                dropdown.appendChild(row);
            });

            isDropdownOpen = true;
            dropdown.style.display = 'block';
            updatePosition();
        }

        let isProgrammaticChange = false;

        function hideDropdown() {
            clearTimeout(debounceTimer);
            isDropdownOpen = false;
            dropdown.style.display = 'none';
            dropdown.innerHTML = '';
            activeIndex = -1;
        }

        function selectItem(item) {
            if (!item || !item.full_address) return;
            isProgrammaticChange = true;
            clearTimeout(debounceTimer);
            
            input.value = item.full_address;
            hideDropdown();

            // Dispatch events for form validation & tracking
            input.dispatchEvent(new Event('change', { bubbles: true }));

            // Reset guard after short cooldown
            setTimeout(function() {
                isProgrammaticChange = false;
            }, 500);

            // Visual feedback on the input
            input.classList.add('address-selected-glow');
            setTimeout(function() {
                input.classList.remove('address-selected-glow');
            }, 1200);
        }

        function highlightItem(index) {
            const rows = dropdown.querySelectorAll('.address-autocomplete-item');
            rows.forEach((r, idx) => {
                if (idx === index) {
                    r.classList.add('is-active');
                    r.scrollIntoView({ block: 'nearest' });
                } else {
                    r.classList.remove('is-active');
                }
            });
        }

        async function fetchSuggestions(query) {
            if (isProgrammaticChange) return;
            try {
                const res = await fetch(getApiUrl(query));
                if (!res.ok) throw new Error('API returned status ' + res.status);
                const data = await res.json();
                if (!isProgrammaticChange) {
                    renderSuggestions(data);
                }
            } catch (err) {
                console.warn('Address autocomplete fetch error:', err);
            }
        }

        // Event delegation on dropdown for 100% reliable clicks
        dropdown.addEventListener('pointerdown', function(e) {
            e.stopPropagation();
        });

        dropdown.addEventListener('mousedown', function(e) {
            e.preventDefault(); // Prevent input from blurring prematurely
        });

        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const row = e.target.closest('.address-autocomplete-item');
            if (!row) return;
            const idx = parseInt(row.dataset.index, 10);
            if (!isNaN(idx) && currentItems[idx]) {
                selectItem(currentItems[idx]);
            }
        });

        // Typing event with debounce
        input.addEventListener('input', function() {
            if (isProgrammaticChange) return;
            const query = this.value.trim();
            clearTimeout(debounceTimer);
            if (query.length === 0) {
                hideDropdown();
                return;
            }
            debounceTimer = setTimeout(function() {
                if (!isProgrammaticChange) {
                    fetchSuggestions(query);
                }
            }, 180);
        });

        // Focus and click on input shows suggestions if empty or user wants to re-type
        input.addEventListener('focus', function() {
            if (isProgrammaticChange) return;
            if (this.value.trim() === '') {
                fetchSuggestions('');
            }
        });

        input.addEventListener('click', function() {
            if (isProgrammaticChange) return;
            if (!isDropdownOpen && this.value.trim() === '') {
                fetchSuggestions('');
            }
        });

        // Keyboard navigation (Arrow keys + Enter + Esc)
        input.addEventListener('keydown', function(e) {
            if (!isDropdownOpen || dropdown.style.display === 'none' || currentItems.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % currentItems.length;
                highlightItem(activeIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + currentItems.length) % currentItems.length;
                highlightItem(activeIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const targetIdx = (activeIndex >= 0 && activeIndex < currentItems.length) ? activeIndex : 0;
                selectItem(currentItems[targetIdx]);
            } else if (e.key === 'Escape') {
                hideDropdown();
            }
        });

        // Reposition dynamically on scroll or window resize
        window.addEventListener('scroll', updatePosition, true);
        window.addEventListener('resize', updatePosition);

        // Close on clicking outside
        document.addEventListener('pointerdown', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                hideDropdown();
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Auto scan & bind to all address inputs across the app
    function bindAllAddressInputs() {
        const selectors = [
            'input#DiaChiThuongTru',
            'input#DiaChi',
            'input[name="DiaChiThuongTru"]',
            'input[name="DiaChi"]',
            'input.address-autocomplete',
            'input[data-address-autocomplete="true"]'
        ];
        document.querySelectorAll(selectors.join(', ')).forEach(initAddressAutocomplete);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAllAddressInputs);
    } else {
        bindAllAddressInputs();
    }

    // Expose for dynamic bindings
    window.initAddressAutocomplete = initAddressAutocomplete;
    window.bindAllAddressInputs = bindAllAddressInputs;
})();
