(function () {
    var BUSINESS_OPEN_MINUTES = (8 * 60) + 30;
    var BUSINESS_CLOSE_MINUTES = (17 * 60) + 30;
    var MIN_ADVANCE_MINUTES = 24 * 60;

    function normalizeToBusinessHours(date) {
        var normalized = new Date(date);
        normalized.setSeconds(0, 0);

        var minutes = normalized.getMinutes();
        var remainder = minutes % 15;
        if (remainder !== 0) {
            normalized.setMinutes(minutes + (15 - remainder));
        }

        while (true) {
            var totalMinutes = (normalized.getHours() * 60) + normalized.getMinutes();
            if (totalMinutes < BUSINESS_OPEN_MINUTES) {
                normalized.setHours(8, 30, 0, 0);
                break;
            }
            if (totalMinutes > BUSINESS_CLOSE_MINUTES) {
                normalized.setDate(normalized.getDate() + 1);
                normalized.setHours(8, 30, 0, 0);
                continue;
            }
            break;
        }

        return normalized;
    }

    function getNearest15Min() {
        var now = new Date();
        now.setMinutes(now.getMinutes() + MIN_ADVANCE_MINUTES);
        return normalizeToBusinessHours(now);
    }

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatDateTime(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function formatTime(date) {
        return pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function normalizeClassicMeridiem(instance) {
        if (!instance || !instance.altInput) return;
        instance.altInput.value = instance.altInput.value.replace(/\bAM\b/g, 'am').replace(/\bPM\b/g, 'pm');
    }

    function isToday(date) {
        var today = new Date();
        return date.getFullYear() === today.getFullYear()
            && date.getMonth() === today.getMonth()
            && date.getDate() === today.getDate();
    }

    function showCartDateChangeModal() {
        var modal = document.getElementById('cartDateChangeModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function hideCartDateChangeModal() {
        var modal = document.getElementById('cartDateChangeModal');
        if (!modal) return;
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function clearCart() {
        localStorage.removeItem('cart');
        sessionStorage.removeItem('cart');
        if (typeof window.updateCartUI === 'function') {
            window.updateCartUI();
        }
    }

    function handleCartDateChange() {
        var cart = localStorage.getItem('cart');
        if (cart && cart.length > 2) {
            clearCart();
            showCartDateChangeModal();
        }
    }

    function getDaysDiff(pickup, ret) {
        if (!pickup || !ret) return 1;
        var start = new Date(pickup);
        var end = new Date(ret);
        if (isNaN(start) || isNaN(end)) return 1;
        var diff = (end - start) / (1000 * 60 * 60 * 24);
        return diff > 0 ? Math.ceil(diff) : 1;
    }

    var quoteCache = new Map();

    function normalizeVariationId(variationId) {
        var value = String(variationId == null ? '' : variationId).trim();
        if (!value || value.toLowerCase() === 'null' || value === '0') return 'null';
        return value;
    }

    async function getTieredPrice(productId, variationId, days, fallbackPrice) {
        var normalizedDays = Math.min(Math.max(parseInt(days, 10) || 1, 1), 31);
        var normalizedVariation = normalizeVariationId(variationId);
        var cacheKey = String(productId) + '|' + normalizedVariation + '|' + String(normalizedDays);

        if (quoteCache.has(cacheKey)) {
            return quoteCache.get(cacheKey);
        }

        try {
            var query = new URLSearchParams({
                product_id: String(productId),
                variation_id: normalizedVariation,
                days: String(normalizedDays)
            });
            var response = await fetch('/api/rental-price?' + query.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                throw new Error('Quote request failed: ' + response.status);
            }
            var payload = await response.json();
            var price = Number(payload.price);
            if (!Number.isFinite(price) || price < 0) {
                price = Number(fallbackPrice || 0);
            }
            quoteCache.set(cacheKey, price);
            return price;
        } catch (err) {
            return Number(fallbackPrice || 0);
        }
    }

    async function updateDaysAndPrices() {
        var pickupInput = document.getElementById('pickupDatetime');
        var returnInput = document.getElementById('returnDatetime');
        var pickup = (pickupInput && pickupInput.value) || localStorage.getItem('pickupDatetime');
        var ret = (returnInput && returnInput.value) || localStorage.getItem('returnDatetime');

        if (!pickup || !ret) return;

        var days = getDaysDiff(pickup, ret);

        var priceSpans = Array.from(document.querySelectorAll('.equipment-price'));
        await Promise.all(priceSpans.map(async function (span) {
            var basePrice = parseFloat(span.dataset.basePrice || '0');
            var productId = span.dataset.productId;
            var variationId = span.dataset.variationId;
            var price = await getTieredPrice(productId, variationId, days, basePrice);

            span.textContent = '$' + Number(price).toFixed(2);
        }));
    }

    async function updateInstantMobilityPrices() {
        var cards = Array.from(document.querySelectorAll('[data-instant-product]'));
        await Promise.all(cards.map(async function (card) {
            var productId = card.getAttribute('data-product-id');
            var variationId = card.getAttribute('data-variation-id');
            var basePrice = Number(card.getAttribute('data-base-price') || 0);
            var dropdown = card.querySelector('.instant-days-dropdown');
            var days = dropdown ? (parseInt(dropdown.value, 10) || 1) : 1;
            var price = await getTieredPrice(productId, variationId, days, basePrice);

            var priceElem = card.querySelector('.instant-mobility-price');
            if (priceElem) {
                priceElem.textContent = '$' + price.toFixed(2);
            }
        }));
    }

    function emphasizeRentalForm() {
        var rentalForm = document.getElementById('rentalForm');
        var rentalFormCard = document.getElementById('rentalFormCard');
        if (!rentalForm || !rentalFormCard) return;

        rentalForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        rentalFormCard.classList.add('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
        setTimeout(function () {
            rentalFormCard.classList.remove('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
        }, 3000);
    }

    async function fastCheckoutProduct(product, event) {
        if (event) event.stopPropagation();

        var handednessSelection = '';
        if (typeof window.requirePowerChairHandednessSelection === 'function') {
            handednessSelection = await window.requirePowerChairHandednessSelection({
                name: product && product.name ? product.name : '',
                category: product && product.category ? product.category : ''
            });
        }
        var isPowerChair = typeof window.isPowerChairProductName === 'function'
            ? window.isPowerChairProductName(product && product.name ? product.name : '', product && product.category ? product.category : '')
            : false;
        if (isPowerChair && handednessSelection === null) {
            return;
        }

        var normalizeHandedness = typeof window.normalizePowerChairHandedness === 'function'
            ? window.normalizePowerChairHandedness
            : function (value) {
                var raw = String(value || '').trim().toLowerCase();
                if (raw === 'left' || raw === 'left-handed' || raw === 'lefthanded') return 'left';
                if (raw === 'right' || raw === 'right-handed' || raw === 'righthanded') return 'right';
                return '';
            };
        var appendHandednessToName = typeof window.appendPowerChairHandednessToName === 'function'
            ? window.appendPowerChairHandednessToName
            : function (name, handedness) {
                var label = normalizeHandedness(handedness) === 'left' ? 'Left-Handed' : (normalizeHandedness(handedness) === 'right' ? 'Right-Handed' : '');
                if (!label || !name) return String(name || '');
                return String(name) + ' - ' + label;
            };
        var normalizedHandedness = normalizeHandedness(handednessSelection);

        localStorage.removeItem('cart');

        var days = 1;
        var selector = '.instant-days-dropdown#instant-days-' + product.id + '-' + product.variation_id;
        var dropdown = document.querySelector(selector);
        if (dropdown) {
            days = parseInt(dropdown.value, 10) || 1;
        }

        var price = await getTieredPrice(product.id, product.variation_id, days, Number(product.price));

        var pickupDate = getNearest15Min();
        var returnDate = new Date(pickupDate);
        returnDate.setDate(returnDate.getDate() + days);

        localStorage.setItem('pickupDatetime', formatDateTime(pickupDate));
        localStorage.setItem('returnDatetime', formatDateTime(returnDate));

        var cartItem = {
            id: product.id,
            name: product.variation_name ? product.name + ' - ' + product.variation_name : product.name,
            price: price,
            qty: 1,
            image_url: product.image_url,
            scooter_count: product.scooter_count
        };

        if (product.variation_id) cartItem.variation_id = product.variation_id;
        if (product.variation_name) cartItem.variation_name = product.variation_name;
        if (normalizedHandedness) {
            cartItem.power_chair_handedness = normalizedHandedness;
            cartItem.name = appendHandednessToName(cartItem.name, normalizedHandedness);
        }

        localStorage.setItem('cart', JSON.stringify([cartItem]));
        window.location.href = '/checkout';
    }

    function initFlatpickr() {
        if (typeof window.flatpickr !== 'function') return;
        if (document.getElementById('dateTimeSelectionModal')) return;

        var pickupInput = document.getElementById('pickupDatetime');
        var returnInput = document.getElementById('returnDatetime');
        if (!pickupInput || !returnInput) return;

        var returnPicker;
        var pickupPicker = window.flatpickr(pickupInput, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'F j, Y h:i K',
            minDate: getNearest15Min(),
            defaultDate: getNearest15Min(),
            time_24hr: false,
            minuteIncrement: 15,
            onReady: function (selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onValueUpdate: function (selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onOpen: function (selectedDates, dateStr, instance) {
                var selected = selectedDates[0] || (instance.input.value ? new Date(instance.input.value) : new Date());
                instance.set('minTime', isToday(selected) ? formatTime(getNearest15Min()) : null);
            },
            onChange: function (selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    instance.set('minTime', isToday(selectedDates[0]) ? formatTime(getNearest15Min()) : null);
                    if (returnPicker) {
                        returnPicker.set('minDate', selectedDates[0]);
                    }
                    if (returnInput.value && new Date(returnInput.value) < selectedDates[0]) {
                        returnInput.value = '';
                        if (returnPicker) returnPicker.clear();
                    }
                }
            }
        });

        returnPicker = window.flatpickr(returnInput, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'F j, Y h:i K',
            minDate: getNearest15Min(),
            time_24hr: false,
            minuteIncrement: 15,
            onReady: function (selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onValueUpdate: function (selectedDates, dateStr, instance) {
                normalizeClassicMeridiem(instance);
            },
            onOpen: function (selectedDates, dateStr, instance) {
                var selected = selectedDates[0] || (instance.input.value ? new Date(instance.input.value) : new Date());
                instance.set('minTime', isToday(selected) ? formatTime(getNearest15Min()) : null);
            },
            onChange: function (selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    instance.set('minTime', isToday(selectedDates[0]) ? formatTime(getNearest15Min()) : null);
                }
            }
        });
    }

    function initTestimonials() {
        var items = document.querySelectorAll('#testimonial-carousel .testimonial-item');
        var prevBtn = document.getElementById('testimonial-prev');
        var nextBtn = document.getElementById('testimonial-next');
        if (!items.length || !prevBtn || !nextBtn) return;

        var startIdx = 0;
        var animating = false;

        function showTestimonials(direction) {
            if (animating) return;
            animating = true;

            var visible = [];
            items.forEach(function (item, idx) {
                if (idx >= startIdx && idx < startIdx + 3) visible.push(item);
            });

            if (direction) {
                visible.forEach(function (item) {
                    item.classList.remove('slide-in-left', 'slide-in-right');
                    item.classList.add(direction === 'left' ? 'slide-out-left' : 'slide-out-right');
                });

                setTimeout(function () {
                    visible.forEach(function (item) {
                        item.style.display = 'none';
                        item.classList.remove('slide-out-left', 'slide-out-right');
                    });

                    items.forEach(function (item, idx) {
                        if (idx >= startIdx && idx < startIdx + 3) {
                            item.style.display = 'flex';
                            item.classList.add(direction === 'left' ? 'slide-in-right' : 'slide-in-left');
                            setTimeout(function () {
                                item.classList.remove('slide-in-left', 'slide-in-right');
                            }, 400);
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    animating = false;
                }, 400);
            } else {
                items.forEach(function (item, idx) {
                    item.style.display = (idx >= startIdx && idx < startIdx + 3) ? 'flex' : 'none';
                    item.classList.remove('slide-in-left', 'slide-in-right', 'slide-out-left', 'slide-out-right');
                });
                animating = false;
            }

            var showArrows = items.length > 3;
            prevBtn.style.display = showArrows ? 'block' : 'none';
            nextBtn.style.display = showArrows ? 'block' : 'none';
        }

        nextBtn.addEventListener('click', function () {
            if (animating) return;
            startIdx += 3;
            if (startIdx >= items.length) startIdx = 0;
            showTestimonials('left');
        });

        prevBtn.addEventListener('click', function () {
            if (animating) return;
            startIdx -= 3;
            if (startIdx < 0) startIdx = Math.max(0, items.length - (items.length % 3 || 3));
            showTestimonials('right');
        });

        showTestimonials();
    }

    function initStepCardsObserver() {
        var cards = document.querySelectorAll('.step-card');
        if (!cards.length || typeof IntersectionObserver === 'undefined') return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('opacity-100', 'translate-y-0');
                }
            });
        }, { threshold: 0.2 });

        cards.forEach(function (card) {
            observer.observe(card);
        });
    }

    function initHomepage() {
        var pickupInput = document.getElementById('pickupDatetime');
        var returnInput = document.getElementById('returnDatetime');
        var searchForm = document.querySelector('form[action="/search"]');
        var usesModalPicker = !!document.getElementById('dateTimeSelectionModal');

        if (!usesModalPicker && pickupInput && localStorage.getItem('pickupDatetime')) {
            pickupInput.value = localStorage.getItem('pickupDatetime');
        }
        if (!usesModalPicker && returnInput && localStorage.getItem('returnDatetime')) {
            returnInput.value = localStorage.getItem('returnDatetime');
        }

        var dateInputs = [pickupInput, returnInput].filter(Boolean);
        if (!usesModalPicker) {
            dateInputs.forEach(function (input) {
            var key = input.id;
            input.addEventListener('change', function (e) {
                localStorage.setItem(key, input.value);
                if (typeof window.updateEquipmentPrices === 'function') window.updateEquipmentPrices();
                handleCartDateChange();
                if (e && typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            }, true);
            input.addEventListener('input', function (e) {
                localStorage.setItem(key, input.value);
                handleCartDateChange();
                if (typeof window.updateEquipmentPrices === 'function') window.updateEquipmentPrices();
                if (e && typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            }, true);
            });
        }

        var closeBtn = document.getElementById('cartDateChangeModalCloseBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideCartDateChangeModal);
        }

        if (searchForm) {
            searchForm.addEventListener('submit', function (e) {
                var pickup = pickupInput ? pickupInput.value : '';
                var ret = returnInput ? returnInput.value : '';
                if (!pickup || !ret) {
                    e.preventDefault();
                    var msg = document.getElementById('formMessage');
                    if (msg) {
                        msg.classList.remove('hidden');
                        setTimeout(function () { msg.classList.add('hidden'); }, 3000);
                    }
                    emphasizeRentalForm();
                }
            });
        }

        var rentNowBtn = document.getElementById('rentNowBtn');
        var heroRentNowBtn = document.getElementById('heroRentNowBtn');
        function emphasizeFormButton(e) {
            if (e) e.preventDefault();
            var overlay = document.getElementById('formOverlay');
            var rentalForm = document.getElementById('rentalForm');
            var rentalFormCard = document.getElementById('rentalFormCard');
            if (!rentalForm || !rentalFormCard) return;

            rentalForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (overlay) overlay.classList.remove('hidden');
            rentalFormCard.classList.add('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
            setTimeout(function () {
                if (overlay) overlay.classList.add('hidden');
                rentalFormCard.classList.remove('ring-4', 'ring-blue-400', 'shadow-2xl', 'z-50', 'relative');
            }, 5000);
        }

        if (rentNowBtn) rentNowBtn.addEventListener('click', emphasizeFormButton);
        if (heroRentNowBtn) heroRentNowBtn.addEventListener('click', emphasizeFormButton);

        document.querySelectorAll('.instant-days-dropdown').forEach(function (dropdown) {
            dropdown.addEventListener('change', updateInstantMobilityPrices);
            dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
            dropdown.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            dropdown.addEventListener('touchstart', function (e) { e.stopPropagation(); });
        });

        var instantDate = document.getElementById('instantMobilityDate');
        var instantTime = document.getElementById('instantMobilityTime');
        if (instantDate && instantTime) {
            var nearest = getNearest15Min();
            instantDate.textContent = nearest.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
            instantTime.textContent = nearest.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true }).replace(' AM', 'am').replace(' PM', 'pm');
        }

        initFlatpickr();
        initTestimonials();
        initStepCardsObserver();
        updateDaysAndPrices();
        updateInstantMobilityPrices();
    }

    window.getDaysDiff = getDaysDiff;
    window.fastCheckoutProduct = fastCheckoutProduct;
    window.emphasizeRentalForm = emphasizeRentalForm;
    window.updateDaysAndPrices = updateDaysAndPrices;

    document.addEventListener('DOMContentLoaded', initHomepage);
})();
