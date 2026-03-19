# TODO: Add Slot Check Per Product in Cart (Shopee-style)

## Task: Add slot/stock check per product in cart.php similar to Shopee

### Steps:
1. [x] Modify cart.php to fetch stock info for each cart item
   - Use rbj_resolve_item_stock() function from shapi_catalog_helper.php
   - Get available stock per product customization/choice

2. [x] Add slot display in cart item UI
   - Show "X slot(s) available" or "X slot(s) left" similar to Shopee
   - Display in each cart item row

3. [x] Add CSS styling for slot indicator
   - Green for good stock (>10 slots)
   - Orange for low stock (1-10 slots)
   - Red for very low/out of stock (0-1 slots)

4. [x] Add variant/slot selector dropdown per cart item
   - Show available variants with stock info
   - Allow users to change variant directly in cart
   - Similar to Shopee's variant selector

5. [x] Test the implementation
   - Verify stock displays correctly per item
   - Test with different stock levels

