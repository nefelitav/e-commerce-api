# shop-api

Users
id, name, email, password_hash, role, created_at, updated_at

Categories
id, name, description, parent_id

Products
id, name, description, price, stock_quantity, category_id, created_at, updated_at

Orders
id, user_id, status, total_price, created_at, updated_at

Order_Items
id, order_id, product_id, quantity, unit_price

Reviews
id, user_id, product_id, rating, comment, created_at

Carts
id, user_id, created_at, updated_at

Cart_Items
id, cart_id, product_id, quantity

Wishlists
id, user_id

Wishlist_Items
id, wishlist_id, product_id

Discounts
id, code, description, discount_type, amount, start_date, end_date, usage_limit

Inventory_History
id, product_id, change_type, quantity_changed, previous_quantity, new_quantity, created_at

Suppliers
id, name, contact_info, created_at, updated_at

Shipping / Addresses
id, user_id, address_line1, address_line2, city, state, zip_code, country, phone_number, created_at, updated_at

Audit_Log
id, user_id, action_type, table_name, record_id, old_values, new_values, created_at


2. Categories
POST /categories → create a category
GET /categories → list all categories
GET /categories/:id → get category details
PUT /categories/:id → update category
DELETE /categories/:id → delete category

3. Products
POST /products → create a product
GET /products → list all products (with filters & pagination)
GET /products/:id → get product details
PUT /products/:id → update product
DELETE /products/:id → delete product
GET /products/search?q= → search products by name/description
GET /products/top-selling → list top-selling products
GET /products/category/:categoryId → products in a category

4. Orders
POST /orders → create an order
GET /orders → list all orders (admin)
GET /orders/:id → get order details (including items)
PUT /orders/:id → update order status
DELETE /orders/:id → cancel/delete order
GET /orders/user/:userId → list orders for a user

5. Order Items
POST /orders/:orderId/items → add product to order
PUT /orders/:orderId/items/:itemId → update quantity
DELETE /orders/:orderId/items/:itemId → remove item

6. Reviews
POST /products/:productId/reviews → add review
GET /products/:productId/reviews → list product reviews
PUT /reviews/:id → update review
DELETE /reviews/:id → delete review

7. Cart
POST /cart → create a cart (usually auto-created for user)
GET /cart/:userId → get user’s cart
POST /cart/:cartId/items → add item to cart
PUT /cart/:cartId/items/:itemId → update item quantity
DELETE /cart/:cartId/items/:itemId → remove item

8. Wishlist
POST /wishlist → create wishlist
GET /wishlist/:userId → get user’s wishlist
POST /wishlist/:wishlistId/items → add item
DELETE /wishlist/:wishlistId/items/:itemId → remove item

9. Discounts
POST /discounts → create discount/coupon
GET /discounts → list all discounts
GET /discounts/:id → get discount details
PUT /discounts/:id → update discount
DELETE /discounts/:id → delete discount
POST /orders/:orderId/apply-discount → apply discount to order

10. Advanced / Optional
GET /inventory-history/:productId → see stock changes
GET /suppliers → list suppliers
GET /addresses/:userId → get user addresses
POST /addresses → add address
PUT /addresses/:id → update address
DELETE /addresses/:id → remove address
GET /audit-log → list all audit actions (admin only)


1. Basic Filters
GET /products?categoryId=1
GET /products?minPrice=10&maxPrice=100
GET /products?inStock=true
GET /products?minRating=4

2. Text Search
GET /products?search=phone

3. Sorting
GET /products?sort=price_asc or sort=price_desc
GET /products?sort=newest
GET /products?sort=top_selling
GET /products?sort=rating_desc

4. Advanced / Optional Filters
GET /products?categoryIds=1,2,5
GET /products?onSale=true
GET /products?supplierId=3
GET /products?categoryId=2&minPrice=20&maxPrice=200&inStock=true&sort=rating_desc

5. Pagination
GET /products?page=2&limit=20