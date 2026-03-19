# TODO: Cart Page Updates - COMPLETED

## Task: Remove tax, improve design, and selected items only checkout

### Changes Made:
1. **Removed Tax** - Removed 8% tax calculation from cart
2. **Improved Order Summary Design** - Shopee-like styling with:
   - Card-style layout with border
   - Items row
   - Shipping section showing J&T Express (₱95.00) and SPX Express (₱120.00) options
   - Clean total row with prominent green price display
3. **Selected Items Only Checkout** - Only checked items go to buy_now.php:
   - Modified cart.php to pass selected item IDs to buy_now.php
   - Modified buy_now.php to load only selected cart items
   - Modified checkout to delete only selected items after order

### Files Edited:
- USER/cart.php
- USER/buy_now.php

### Status: COMPLETED

