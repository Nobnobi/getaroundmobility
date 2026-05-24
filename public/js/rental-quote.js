(function () {
    if (typeof window === 'undefined') return;

    var quoteCache = new Map();

    function normalizeVariationId(variationId) {
        var value = String(variationId == null ? '' : variationId).trim();
        if (!value || value.toLowerCase() === 'null' || value === '0') return 'null';
        return value;
    }

    function normalizeDays(days) {
        var parsed = parseInt(days, 10);
        if (!Number.isFinite(parsed) || parsed < 1) return 1;
        if (parsed > 31) return 31;
        return parsed;
    }

    async function fetchRentalQuotePrice(productId, variationId, days, fallbackPrice) {
        var safeProductId = parseInt(productId, 10) || 0;
        if (safeProductId <= 0) {
            return Number(fallbackPrice || 0);
        }

        var safeDays = normalizeDays(days);
        var safeVariationId = normalizeVariationId(variationId);
        var cacheKey = String(safeProductId) + '|' + safeVariationId + '|' + String(safeDays);

        if (quoteCache.has(cacheKey)) {
            return quoteCache.get(cacheKey);
        }

        try {
            var query = new URLSearchParams({
                product_id: String(safeProductId),
                variation_id: safeVariationId,
                days: String(safeDays)
            });

            var response = await fetch('/api/rental-price?' + query.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('Quote endpoint error: ' + response.status);
            }

            var data = await response.json();
            var amount = Number(data && data.price);
            if (!Number.isFinite(amount) || amount < 0) {
                amount = Number(fallbackPrice || 0);
            }

            quoteCache.set(cacheKey, amount);
            return amount;
        } catch (err) {
            return Number(fallbackPrice || 0);
        }
    }

    window.fetchRentalQuotePrice = fetchRentalQuotePrice;
})();
