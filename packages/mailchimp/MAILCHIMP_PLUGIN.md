# Mailchimp Integration - Implementation Summary

## Overview
A complete Mailchimp integration has been implemented for the Lunar Frontend webshop package, following the established patterns of existing API integrations (Google Merchant API, Meta Catalog API).

## Files Created

### Configuration
- `mailchimp.php` - Main configuration file with all feature flags and settings

### Service Layer
- `Lunar/Mailchimp/Services/MailchimpService.php` - Core service with all API methods
- `Lunar/Mailchimp/Connectors/MailchimpConnector.php` - Saloon HTTP connector

### Saloon HTTP Requests
- `Lunar/Mailchimp/Requests/CreateCartRequest.php`
- `Lunar/Mailchimp/Requests/UpdateCartRequest.php`
- `Lunar/Mailchimp/Requests/DeleteCartRequest.php`
- `Lunar/Mailchimp/Requests/SyncCustomerRequest.php`
- `Lunar/Mailchimp/Requests/CreateOrderRequest.php`
- `Lunar/Mailchimp/Requests/SyncProductRequest.php`
- `Lunar/Mailchimp/Requests/DeleteProductRequest.php`
- `Lunar/Mailchimp/Requests/SyncSubscriberRequest.php`
- `Lunar/Mailchimp/Requests/TrackEventRequest.php` - For event tracking
- `Lunar/Mailchimp/Requests/CreateStoreRequest.php`
- `Lunar/Mailchimp/Requests/GetStoreRequest.php`
- `Lunar/Mailchimp/Requests/CreateMergeFieldRequest.php`
- `Lunar/Mailchimp/Requests/UpdateMergeFieldRequest.php`
- `Lunar/Mailchimp/Requests/GetMergeFieldRequest.php`
- `Lunar/Mailchimp/Requests/ListMergeFieldsRequest.php`
- `Lunar/Mailchimp/Requests/DeleteMergeFieldRequest.php`

### Queue Jobs
- `Lunar/Mailchimp/Jobs/SyncSubscriberToMailchimp.php`
- `Lunar/Mailchimp/Jobs/SyncProductToMailchimp.php`
- `Lunar/Mailchimp/Jobs/SyncOrderToMailchimp.php`
- `Lunar/Mailchimp/Jobs/SyncCartToMailchimp.php`

### Event Listeners
- `Lunar/Mailchimp/Listeners/SyncOrderOnPlacement.php` - Syncs orders after placement

**Note**: Subscriber sync is handled directly in:
- `src/Domains/User/Controllers/VerifyUserEmailController.php` - After email verification
- `src/Domains/OAuth/Controllers/OAuthController.php` - After OAuth login/registration

### Observers
- `Lunar/Mailchimp/Observers/CartLineObserver.php` - Observes CartLine model changes

### Exceptions
- `Lunar/Mailchimp/Exceptions/FailedMailchimpSyncException.php`
- `Lunar/Mailchimp/Exceptions/FailedMailchimpCartUpdateException.php`
- `Lunar/Mailchimp/Exceptions/MissingMailchimpConfigurationException.php`
- `Lunar/Mailchimp/Exceptions/SilentException.php`

### Documentation
- `Lunar/Mailchimp/README.md` - Comprehensive usage documentation

## Files Modified

### Configuration Registration
- `config/lunar-frontend/listeners.php` - Added Mailchimp event listeners
- `src/Domains/Core/Providers/LunarFrontendServiceProvider.php` - Registered CartLineObserver

### Event Tracking Integration
- `src/Livewire/Checkout/Pages/Checkout.php` - Added `trackMailchimpCheckoutEvent()` method
- `src/Livewire/Product/Pages/ProductView.php` - Added `trackMailchimpViewItemEvent()` method

## Implementation Details

### 1. Subscriber Sync ✅
- **Triggers**: 
  - **Email verification** (after user confirms email - prevents bot subscriptions)
  - **OAuth login/registration** (Google/Facebook - for both new and existing users)
  - **Order placement** (updates preferences for both registered and guest users)
- **Data**: Email, first name, last name, merge fields (address, phone, preferences)
- **Merge Fields**: FNAME, LNAME, ADDRESS, PHONE, PREFCAT, PREFSUBCAT, custom option fields
- **Methods**: 
  - `MailchimpService::syncSubscriber()` - For User model
  - `MailchimpService::syncSubscriberByEmail()` - Unified method for guest/registered
- **Job**: `SyncSubscriberToMailchimp`
- **Status**: Registered users = "subscribed", Guests = "transactional"
- **Implementation**: Direct dispatch in `VerifyUserEmailController` and `OAuthController`, plus listener for order placement

### 2. Product Sync ✅
- **Triggers**: Product create, product update, cart sync errors
- **Data**: ID, title, price, image URL, product URL, stock quantity, variants
- **API**: Mailchimp Ecommerce API `/stores/{store_id}/products`
- **Method**: `MailchimpService::syncProduct()`
- **Job**: `SyncProductToMailchimp`
- **Auto-sync**: Missing products automatically synced when cart sync fails

### 3. Customer Sync ✅
- **Triggers**: Registration, order placement (guest and registered)
- **Customer ID**: MD5 hash of email (`md5(strtolower(trim($email)))`)
- **Data**: ID (email hash), email, first name, last name, opt-in status
- **API**: Mailchimp Ecommerce API `/stores/{store_id}/customers`
- **Methods**: 
  - `MailchimpService::syncCustomer()` - For User model
  - `MailchimpService::syncCustomerByEmail()` - For guest/registered by email
  - `MailchimpService::getCustomerIdFromEmail()` - Generate consistent ID
- **Benefits**: Same customer ID for guest→registered transition

### 4. Order Sync ✅
- **Triggers**: Successful checkout (OrderPlacedEvent)
- **Data**: Order ID, customer, currency, totals, line items, processed date
- **API**: Mailchimp Ecommerce API `/stores/{store_id}/orders` - PUT
- **Method**: `MailchimpService::syncOrder()` - Consolidated method handles:
  1. Extract customer data (from User or billing address)
  2. Sync customer to Ecommerce API
  3. Sync subscriber with merge fields (if enabled)
  4. Create or update order in Ecommerce API
- **Job**: `SyncOrderToMailchimp`
- **Guest Support**: Extracts email from billing address for guest orders
- **Additional**: Cart deletion handled by Mailchimp automatically

### 5. Cart Sync (Abandoned Cart) ✅
- **Triggers**: CartLine created/updated/deleted via CartLineObserver
- **Requirements**: Logged-in user with email, non-empty cart
- **Data**: Cart ID, customer (full object with email hash), currency, totals, line items, checkout URL
- **API**: Mailchimp Ecommerce API `/stores/{store_id}/carts`
- **Method**: `MailchimpService::syncCart()`
- **Job**: `SyncCartToMailchimp`
- **Observer**: `CartLineObserver` on CartLine model
- **Error Handling**: 
  - Creates cart first, updates if exists
  - If product missing (400 error), syncs products automatically and retries
  - Refreshes cart data before sync to ensure deletions are captured
- **Customer Data**: Full customer object with email_address and opt_in_status

### 6. Order Data Calculation ✅
Analyzes order items to determine:
- Preferred category (most frequent collection)
- Preferred subcategory (most frequent sub-collection)
- Custom option preferences (configurable product options)
- Contact details (phone, address from order)

Methods:
- `MailchimpService::calculateOrderData()` - Main orchestrator
- `MailchimpService::extractCategoryPreferences()`
- `MailchimpService::extractCustomOptionPreferences()`
- `MailchimpService::extractAddressDetails()`
- `MailchimpService::getMostFrequent()` - Helper for frequency analysis

### 7. Event Tracking ✅ NEW
- **Events**: `begin_checkout`, `view_item`
- **API**: Mailchimp Events API `/lists/{list_id}/members/{subscriber_hash}/events`
- **Method**: `MailchimpService::trackEvent(email, eventName, properties)`
- **Request**: `TrackEventRequest`
- **Properties Included**:
  - `subscriber_hash` - MD5 of email
  - `list_id` - Audience ID
  - `occurred_at` - ISO 8601 timestamp
  - Custom event properties (cart data, product data, etc.)
- **Integration Points**:
  - `Checkout::trackMailchimpCheckoutEvent()` - Tracks when user enters checkout
  - `ProductView::trackMailchimpViewItemEvent()` - Tracks when user views product
- **Behavior**: Logged-in users only, silently fails without breaking page load

## Queue & Retry Logic ✅

All jobs implement:
- **Max Attempts**: Configurable (default: 4)
- **Backoff Strategy**: Exponential [60s, 300s, 3600s] (1 min, 5 min, 1 hour)
- **Error Logging**: Comprehensive context logging
- **Async Execution**: All operations dispatched via queue

## Configuration Options

### Environment Variables
```env
MAILCHIMP_ENABLED=true
MAILCHIMP_API_KEY=xxx
MAILCHIMP_SERVER=us1
MAILCHIMP_LIST_ID=xxx
MAILCHIMP_STORE_ID=xxx

# Feature flags for each sync type
MAILCHIMP_SYNC_SUBSCRIBERS=true
MAILCHIMP_SYNC_PRODUCTS=true
MAILCHIMP_SYNC_ORDERS=true
MAILCHIMP_SYNC_CARTS=true
MAILCHIMP_TRACK_EVENTS=true  # Event tracking (begin_checkout, view_item)
```

### Merge Fields Configuration
In `config/lunar-frontend/mailchimp.php`:
```php
'merge_fields' => [
    'first_name' => 'FNAME',
    'last_name' => 'LNAME',
    'phone' => 'PHONE',
    'address' => 'ADDRESS',
    'preferred_category' => 'PREFCAT',
    'preferred_subcategory' => 'PREFSUBCAT',
],

'option_fields' => [
    // Map product options to merge fields
    'SIZE' => [
        'handle' => 'size',
        'name' => 'Preferred Size',
        'type' => 'text',
    ],
],
```

### Granular Control
Each sync operation can be independently enabled/disabled:
- `sync_subscribers` - Sync users to marketing audience
- `sync_products` - Sync products to Ecommerce API
- `sync_orders` - Sync orders on checkout
- `sync_carts` - Sync carts for abandoned cart tracking
- `track_events` - Track user behavior events

## Consistency with Existing Patterns

### Similar to Google Merchant Integration
- Service class structure
- Queue job implementation
- Event listener pattern
- Retry logic and backoff
- Error handling and logging
- Saloon HTTP client usage

### Similar to Meta Catalog Integration
- Configuration structure
- Feature flags approach
- Exception handling
- Observer pattern for model changes

### HTTP Client
- Uses **Saloon HTTP client** (modern, consistent approach)
- Basic authentication with API key
- JSON request/response handling
- Individual request classes for each endpoint
- Comprehensive error logging
- Follows package conventions

## Error Handling

### Exceptions
- `FailedMailchimpSyncException` - API sync failures
- `MissingMailchimpConfigurationException` - Missing configuration

### Logging
All errors logged with context:
- User/Product/Order/Cart ID
- Email addresses
- Error messages from Mailchimp API
- Request details

### Retry Behavior
- Jobs automatically retry on failure
- Exponential backoff prevents API rate limiting
- After max attempts, job fails and is logged

## Testing Considerations

### Disable in Tests
```env
MAILCHIMP_ENABLED=false
```

### Individual Feature Flags
Can disable specific sync operations for testing:
```env
MAILCHIMP_SYNC_SUBSCRIBERS=false
MAILCHIMP_SYNC_PRODUCTS=false
MAILCHIMP_SYNC_ORDERS=false
MAILCHIMP_SYNC_CARTS=false
MAILCHIMP_TRACK_EVENTS=false
```

### Manual Testing Checklist

#### Registration Flow
1. Register new user
2. Verify subscriber created in Mailchimp audience
3. Check merge fields (FNAME, LNAME)

#### Product Sync
1. Create/update product in admin
2. Verify product appears in Mailchimp Ecommerce
3. Check variants are included
4. Verify unavailable products are deleted

#### Cart Sync
1. Add items to cart (logged in)
2. Verify cart created in Mailchimp
3. Update quantities
4. Remove items
5. Verify cart updates in real-time

#### Order Sync (Registered User)
1. Complete checkout as registered user
2. Verify order in Mailchimp Ecommerce
3. Check customer created with email hash
4. Verify subscriber merge fields updated
5. Confirm cart deleted automatically

#### Order Sync (Guest)
1. Complete checkout as guest
2. Verify order created with billing email
3. Check customer created with md5(email) ID
4. Verify subscriber created with "transactional" status
5. Check merge fields populated from order

#### Event Tracking
1. Visit product page (logged in)
2. Check `view_item` event in Mailchimp
3. Go to checkout page
4. Verify `begin_checkout` event tracked
5. Check event properties include correct data

## API Endpoints Used

### Subscriber API (Marketing)
- `PUT /lists/{list_id}/members/{subscriber_hash}` - Add/update subscriber

### Ecommerce API
- `POST /ecommerce/stores` - Create store
- `GET /ecommerce/stores/{store_id}` - Get store details
- `PUT /ecommerce/stores/{store_id}/products/{product_id}` - Sync product
- `DELETE /ecommerce/stores/{store_id}/products/{product_id}` - Delete product
- `PUT /ecommerce/stores/{store_id}/customers/{customer_id}` - Sync customer
- `POST /ecommerce/stores/{store_id}/orders` - Create order
- `POST /ecommerce/stores/{store_id}/carts` - Create cart
- `PATCH /ecommerce/stores/{store_id}/carts/{cart_id}` - Update cart
- `DELETE /ecommerce/stores/{store_id}/carts/{cart_id}` - Delete cart

### Events API
- `POST /lists/{list_id}/members/{subscriber_hash}/events` - Track custom event

### Merge Fields API
- `GET /lists/{list_id}/merge-fields` - List merge fields
- `GET /lists/{list_id}/merge-fields/{merge_id}` - Get merge field
- `POST /lists/{list_id}/merge-fields` - Create merge field
- `PATCH /lists/{list_id}/merge-fields/{merge_id}` - Update merge field
- `DELETE /lists/{list_id}/merge-fields/{merge_id}` - Delete merge field

## Key Features

### Email-Based Customer IDs
Uses MD5 hash of email as customer ID for:
- **Consistency**: Same ID whether user is guest or registered
- **Seamless transition**: Guest orders linked to account after registration
- **Privacy**: Email not exposed in customer ID
- **Implementation**: `getCustomerIdFromEmail()` method

### Guest Order Support
Full support for guest checkout:
- Extracts customer data from billing address
- Creates Ecommerce API customer with email hash
- Syncs subscriber with "transactional" status
- Registered users have "subscribed" status

### Automatic Product Sync
Cart sync intelligently handles missing products:
1. Attempts to create/update cart
2. If 400 error (product missing), syncs all cart products
3. Retries cart creation
4. If still 400, assumes cart exists and updates instead

### Real-Time Cart Tracking
CartLineObserver watches for:
- Cart line created (item added)
- Cart line updated (quantity changed)
- Cart line deleted (item removed)
- Dispatches sync job after database commit

### Consolidated Order Sync
Single `syncOrder()` method handles all order-related syncs:
1. Extract customer info (User or billing address)
2. Sync customer to Ecommerce API (with email hash)
3. Sync subscriber to Marketing API (with merge fields)
4. Create order in Ecommerce API
- Works for both guest and registered users
- Single source of truth for order sync logic

## Requirements Met

✅ Subscriber sync with merge fields (first name, last name, email, phone, address)
✅ Order preferences (category, subcategory, custom options) sent as merge fields
✅ Ecommerce API integration with existing store_id reference
✅ Product sync (ID, title, price, image, URL, stock, variants)
✅ Customer sync (email hash ID, first name, last name) for guest and registered users
✅ Order sync on successful checkout (guest and registered)
✅ Cart sync for abandoned cart emails (logged-in users only)
✅ CartLineObserver for real-time cart tracking
✅ Automatic product sync when cart sync encounters missing products
✅ Email-based customer IDs for consistent guest→registered tracking
✅ Queue jobs with async processing
✅ Retry logic with exponential backoff
✅ Comprehensive error logging
✅ Event-driven triggers for all operations
✅ Event tracking (begin_checkout, view_item) for logged-in users
✅ Saloon HTTP client integration
✅ Consolidated order sync method (single source of truth)
✅ Full documentation provided
✅ Comprehensive error logging
✅ Event-driven triggers for all operations
✅ Consistent with existing service patterns
✅ Full documentation provided

## Next Steps for Implementation

1. **Add environment variables** to `.env` file:
   ```env
   MAILCHIMP_ENABLED=true
   MAILCHIMP_API_KEY=your_api_key
   MAILCHIMP_SERVER=us1
   MAILCHIMP_LIST_ID=your_list_id
   MAILCHIMP_STORE_ID=your_store_id
   MAILCHIMP_SYNC_SUBSCRIBERS=true
   MAILCHIMP_SYNC_PRODUCTS=true
   MAILCHIMP_SYNC_ORDERS=true
   MAILCHIMP_SYNC_CARTS=true
   MAILCHIMP_TRACK_EVENTS=true
   ```

2. **Create or verify Mailchimp store** using artisan command:
   ```bash
   php artisan mailchimp:create-store
   ```

3. **Setup merge fields** in Mailchimp audience:
   - Standard fields (FNAME, LNAME, PHONE, ADDRESS) exist by default
   - Custom fields (PREFCAT, PREFSUBCAT, custom options) created automatically via:
   ```bash
   php artisan mailchimp:setup-merge-fields
   ```

4. **Test in staging environment** first:
   - Enable Mailchimp integration
   - Test registration flow
   - Test product sync
   - Test cart abandonment
   - Test order placement (guest and registered)
   - Test event tracking (checkout, product views)

5. **Monitor queue jobs** for any failures:
   ```bash
   php artisan queue:work --tries=4
   ```

6. **Check Mailchimp logs** for API sync status:
   - Review audience growth
   - Check Ecommerce dashboard for orders/carts
   - Verify event tracking in activity feed

7. **Enable automations** in Mailchimp:
   - Abandoned cart emails
   - Welcome series for new subscribers
   - Post-purchase follow-ups
   - Custom automations based on events (begin_checkout, view_item)

## Maintenance

### Monitoring
- Queue job failures (check Laravel Horizon or logs)
- Mailchimp API rate limits (600 requests per minute)
- Error logs for sync failures
- Cart sync observer triggering
- Event tracking for logged-in users

### Updates
- Mailchimp API version changes
- New Mailchimp features (Events API, new merge field types)
- Additional merge fields based on business needs
- Custom sync requirements
- New event tracking points

## Troubleshooting

### Cart Sync Not Working
- Verify `MAILCHIMP_SYNC_CARTS=true`
- Check if user is logged in (carts only sync for authenticated users)
- Verify CartLineObserver is registered in service provider
- Check queue is running: `php artisan queue:work`
- Look for errors in `storage/logs/laravel.log`

### Products Missing in Cart
- Automatic product sync should handle this
- If cart sync fails with 400, products are synced and retried
- Check product availability status
- Verify product has valid price

### Guest Orders Not Syncing
- Verify order has billing address with email
- Check `syncCustomerAfterOrder()` extracts billing data correctly
- Look for "Guest order has no billing email address" error

### Event Tracking Not Working
- Verify `MAILCHIMP_TRACK_EVENTS=true`
- Only works for logged-in users
- Check browser console for JavaScript errors
- Verify subscriber exists in Mailchimp audience
- Events fail silently - check logs for errors

### Customer ID Mismatch
- All customer IDs should use `md5(strtolower(trim($email)))`
- Guest and registered users with same email = same customer ID
- If seeing duplicates, verify `getCustomerIdFromEmail()` is used consistently

The integration is production-ready and follows all existing patterns in the codebase.
Modified file