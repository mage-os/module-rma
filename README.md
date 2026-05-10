# MageOS_RMA

Return Merchandise Authorization (RMA) module for Magento / MageOS.

## Installation

### Via Composer

```bash
composer require mage-os/module-rma
```

### Enable the module

```bash
bin/magento module:enable MageOS_RMA
bin/magento setup:upgrade
bin/magento cache:flush
```

> The `setup:upgrade` command creates the database tables and inserts default data (status, reason, resolution type, item condition) via Data Patches.

## Configuration

Module settings are located at **Stores > Configuration > Sales > RMA - Return Management**.

### General

| Field | Type | Default | Description |
|---|---|---|---|
| Enable RMA | Yes/No | No | Enable or disable the RMA feature (scope: website) |
| Increment ID Prefix | Text | `RMA-` | Prefix for the return increment ID (e.g. `RMA-000001`) |
| Return Period (Days) | Numeric | `30` | Number of days after order placement within which a return can be requested |

### Policy

| Field | Type | Default | Description |
|---|---|---|---|
| Auto-Approve Returns | Yes/No | No | When enabled, new return requests are automatically approved |
| Allowed Order Statuses | Multiselect | `complete` | Only orders with these statuses can have a return request |

### Email Notifications

| Field | Type | Default | Description |
|---|---|---|---|
| Email Sender | Select | `General Contact` | Sender identity for RMA emails |
| New RMA Email Template (Customer) | Select | — | Email template sent to the customer when a new return is created |
| Status Change Email Template (Customer) | Select | — | Email template sent to the customer on return status change |
| New RMA Email Template (Admin) | Select | — | Email template sent to the admin when a new return is created |
| Admin Notification Email | Text (email) | — | Email address to receive admin notifications about returns |

### Attachments

| Field | Type | Default | Description |
|---|---|---|---|
| Allowed File Extensions | Text | `jpg,jpeg,png,gif,webp,mp4,mov,pdf,doc,docx,zip` | Comma-separated list of allowed file extensions |
| Maximum File Size (MB) | Numeric | `10` | Maximum allowed file size in megabytes per single file |
| Maximum Files Per Upload | Numeric | `5` | Maximum number of files allowed per RMA creation or comment |

## Admin Area

### Menu

Module entries are located under **Sales > RMA**:

- **RMA Requests** — main grid with all return requests
- **Statuses** — manage the return lifecycle statuses
- **Reasons** — manage return reasons
- **Resolution Types** — manage resolution types (refund, replacement, etc.)
- **Item Conditions** — manage item conditions (opened, sealed, etc.)

### Creating an RMA from admin

1. Go to **Sales > RMA > RMA Requests**, click **Add New RMA**
2. Search and select an order in the **Order** field (only shows orders from websites with RMA enabled)
3. Customer fields (name, email) and store are automatically filled from the order
4. Select reason and resolution type
5. In the **Items to Return** section, select the order items to include in the return, specifying quantity and condition for each
6. Save — the system automatically generates an increment ID (e.g. `RMA-000001`) and sends notification emails

### Edit RMA

In edit mode, the form shows order and item information as read-only. You can modify status, reason, resolution type and admin notes.

Changing the status automatically sends a notification email to the customer.

### Attachments

The RMA edit page displays a unified **Attachments** section above the comments timeline. This section shows all attachments associated with the RMA, regardless of whether they were uploaded at RMA creation or within a comment.

- Each attachment has a **download** link and a **delete** button
- Deleting an attachment from the unified section also removes it from the corresponding comment in the timeline (and vice versa)
- The section updates dynamically when new comments with attachments arrive via polling

### Comments / Chat

The RMA edit page includes a **Comments** section that enables communication between admin and customer.

- Admin can write comments visible to the customer or **internal notes** (not visible to the customer) via the "Visible to Customer" checkbox
- Internal notes display an **Internal Note** badge in the timeline
- Admin can attach files to comments via drag & drop or file picker
- Comments update in real time via AJAX polling with progressive backoff (10s → 30s → 60s)
- Polling pauses when the browser tab is not visible and resumes when it becomes active again
- Messages can be sent with **Ctrl+Enter** in addition to the button

## Frontend — Customer

### My Returns

A **My Returns** link appears in the customer account sidebar. The page shows all of the customer's returns sorted by date descending, with pagination.

Table columns:

| Column | Description |
|---|---|
| RMA # | Return increment ID (e.g. `RMA-000001`) |
| Order # | Associated order increment ID |
| Status | Current return status (translated per store view) |
| Created At | Creation date |
| Action | **View** link to the detail page |

The **Request Return** button at the top leads to the creation form.

### RMA Detail

The page displays return information and the items table:

- **General information**: increment ID, order, status, reason, preferred resolution, creation and update dates
- **Items table**: product name, SKU, requested quantity, item condition
- **Attachments section**: all attachments uploaded across the RMA lifecycle (creation and comments) displayed in a unified list with download links. The section is always present and updates dynamically when new comments with attachments arrive
- All labels (status, reason, resolution, condition) are translated according to the current store view

#### Customer Comments / Chat

Below the detail section there is a comments area that allows the customer to communicate with support:

- The customer only sees comments marked as visible (admin internal notes are not shown)
- Admin messages display a **Support** badge
- Customers can attach files to comments via drag & drop or file picker
- Attachments are also shown inline within each comment for context
- Same real-time polling mechanism as the admin side (backoff 10s → 30s → 60s, pauses on hidden tab)
- Submit with **Ctrl+Enter** or the **Send** button

### Creating an RMA from the customer area

1. From **My Returns**, click **Request Return**
2. Select an order from the dropdown (only eligible orders are shown — see Eligibility section)
3. On order change, available items are loaded via AJAX
4. For each item: check the checkbox, specify quantity and condition
5. Select reason and preferred resolution
6. Optionally attach files via drag & drop or file picker (allowed extensions and size limits are configurable — see Attachments configuration)
7. Click **Submit Return Request**

If arriving from the **Request Return** button on the order detail page, the order is pre-selected and the dropdown is disabled.

### "Request Return" button on order detail

On the customer order detail page (**Sales > My Orders > View Order**), a **Request Return** button appears in the action bar if the order is eligible for a return. Clicking it leads to the creation form with the order pre-selected.

## Frontend — Guest

Guests (orders without an account) can request a return from the guest order detail page:

1. Access the guest order detail via **Orders and Returns** (order number + email/ZIP)
2. If the order is eligible, the **Request Return** button appears
3. The form is identical to the logged-in customer form, but without the order dropdown (the order is already determined)
4. After submission, the return is created with `customer_id = null`

> Guests do not have a "My Returns" section — they can only create returns from the order detail page.

## Order Eligibility for RMA

An order is eligible for a return request if **all** of these conditions are met:

1. **RMA enabled** — the module is enabled for the order's website (`isEnabled()`)
2. **Allowed status** — the order status is among those configured in "Allowed Order Statuses"
3. **Return period** — the order date is within the period configured in "Return Period (Days)". If the period is `0`, returns are always allowed (no time limit)
4. **Available items** — the order has at least one item with remaining returnable quantity (qty ordered − qty already requested in other RMAs > 0). Virtual items, downloadable items, and parent items of configurable/bundle products are excluded

The logic is centralized in the `Service\OrderEligibility` service with the following methods:

| Method | Description |
|---|---|
| `isOrderEligible(OrderInterface $order): bool` | Checks all 4 conditions |
| `getEligibleItems(OrderInterface $order): array` | Returns items with available quantity |
| `getCustomerEligibleOrders(int $customerId, int $storeId): Collection` | Customer orders matching conditions 1-3 |

## Repositories and Service Contracts

Each entity exposes a repository with interfaces in `Api/`:

| Interface | Implementation | Methods |
|---|---|---|
| `RMARepositoryInterface` | `Model\RMARepository` | `get`, `getByIncrementId`, `save`, `delete`, `deleteById`, `getList` |
| `StatusRepositoryInterface` | `Model\StatusRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |
| `ReasonRepositoryInterface` | `Model\ReasonRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |
| `ResolutionTypeRepositoryInterface` | `Model\ResolutionTypeRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |
| `ItemConditionRepositoryInterface` | `Model\ItemConditionRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |
| `ItemRepositoryInterface` | `Model\ItemRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |
| `CommentRepositoryInterface` | `Model\CommentRepository` | `get`, `save`, `delete`, `deleteById`, `getList` |

All `getList` methods support `SearchCriteriaInterface` for filtering, sorting and pagination.

## Business Events

The module dispatches custom events in `RMARepository::save()` to allow other modules to react to return lifecycle changes.

### How dispatching works

1. **Creation**: when a new RMA is saved, `rma_created_after` fires
2. **Status change**: when the status changes, **two** events fire in sequence:
   - `rma_status_change_after` (generic, fires on any transition)
   - A specific semantic event based on the new status (e.g. `rma_approved_after`)

### Events table

| Event | When it fires | Available data |
|---|---|---|
| `rma_created_after` | New RMA created | `rma` |
| `rma_status_change_after` | Any status change | `rma`, `old_status_id`, `new_status_id` |
| `rma_approved_after` | Status &rarr; Approved | `rma`, `old_status_id`, `new_status_id` |
| `rma_rejected_after` | Status &rarr; Rejected | `rma`, `old_status_id`, `new_status_id` |
| `rma_shipped_by_customer_after` | Status &rarr; Shipped by Customer | `rma`, `old_status_id`, `new_status_id` |
| `rma_received_after` | Status &rarr; Received by Admin | `rma`, `old_status_id`, `new_status_id` |
| `rma_canceled_after` | Status &rarr; Canceled by Customer | `rma`, `old_status_id`, `new_status_id` |
| `rma_resolved_after` | Status &rarr; Resolved | `rma`, `old_status_id`, `new_status_id` |

### Example: Observer to book a carrier when a return is approved

**`etc/events.xml`** in your custom module:

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="rma_approved_after">
        <observer name="my_module_book_carrier"
                  instance="MyVendor\MyModule\Observer\BookCarrierPickup"/>
    </event>
</config>
```

**`Observer/BookCarrierPickup.php`**:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyModule\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\RMA\Api\Data\RMAInterface;

class BookCarrierPickup implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var RMAInterface $rma */
        $rma = $observer->getData('rma');
        $oldStatusId = $observer->getData('old_status_id');
        $newStatusId = $observer->getData('new_status_id');

        // Your carrier booking logic here
        // $rma->getCustomerEmail(), $rma->getOrderId(), etc.
    }
}
```

### Example: Generic observer to log all status changes

```xml
<event name="rma_status_change_after">
    <observer name="my_module_log_status_change"
              instance="MyVendor\MyModule\Observer\LogStatusChange"/>
</event>
```

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyModule\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class LogStatusChange implements ObserverInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $rma = $observer->getData('rma');

        $this->logger->info('RMA status changed', [
            'rma_id' => $rma->getEntityId(),
            'increment_id' => $rma->getIncrementId(),
            'old_status_id' => $observer->getData('old_status_id'),
            'new_status_id' => $observer->getData('new_status_id'),
        ]);
    }
}
```

## REST API

All endpoints require an admin integration token (`Authorization: Bearer <token>`) and are protected by ACL resources.

### RMA Entity

| Method | Endpoint | ACL | Description |
|---|---|---|---|
| `GET` | `/V1/rma/:entityId` | `MageOS_RMA::rma_manage` | Get RMA by ID |
| `GET` | `/V1/rma/increment-id/:incrementId` | `MageOS_RMA::rma_manage` | Get RMA by increment ID |
| `POST` | `/V1/rma` | `MageOS_RMA::rma_manage` | Create RMA |
| `PUT` | `/V1/rma/:entityId` | `MageOS_RMA::rma_manage` | Update RMA |
| `DELETE` | `/V1/rma/:entityId` | `MageOS_RMA::rma_manage` | Delete RMA |
| `GET` | `/V1/rma/search` | `MageOS_RMA::rma_manage` | Search RMAs (SearchCriteria) |

### RMA Items

Items are managed as a **sub-resource** of the parent RMA, following the same pattern as Magento core (e.g. `OrderInterface` / `OrderItemRepositoryInterface`). The `GET /V1/rma/:entityId` endpoint returns the RMA header only — to retrieve items, use the dedicated endpoints below.

| Method | Endpoint | ACL | Description |
|---|---|---|---|
| `GET` | `/V1/rma/:rmaId/items` | `MageOS_RMA::rma_manage` | List items for an RMA (SearchCriteria) |
| `POST` | `/V1/rma/:rmaId/items` | `MageOS_RMA::rma_manage` | Add item to an RMA |
| `DELETE` | `/V1/rma/:rmaId/items/:itemId` | `MageOS_RMA::rma_manage` | Remove item from an RMA |

### RMA Comments

Comments are managed as a **sub-resource** of the parent RMA, same as items above.

| Method | Endpoint | ACL | Description |
|---|---|---|---|
| `GET` | `/V1/rma/:rmaId/comments` | `MageOS_RMA::rma_manage` | List comments for an RMA (SearchCriteria) |
| `POST` | `/V1/rma/:rmaId/comments` | `MageOS_RMA::rma_manage` | Add comment to an RMA |

### Lookup Entities

Each lookup entity (status, reason, resolution type, item condition) exposes full CRUD endpoints.

#### Statuses

| Method | Endpoint | ACL |
|---|---|---|
| `GET` | `/V1/rma/status/:entityId` | `MageOS_RMA::rma_status` |
| `POST` | `/V1/rma/status` | `MageOS_RMA::rma_status` |
| `PUT` | `/V1/rma/status/:entityId` | `MageOS_RMA::rma_status` |
| `DELETE` | `/V1/rma/status/:entityId` | `MageOS_RMA::rma_status` |
| `GET` | `/V1/rma/status/search` | `MageOS_RMA::rma_status` |

#### Reasons

| Method | Endpoint | ACL |
|---|---|---|
| `GET` | `/V1/rma/reason/:entityId` | `MageOS_RMA::rma_reason` |
| `POST` | `/V1/rma/reason` | `MageOS_RMA::rma_reason` |
| `PUT` | `/V1/rma/reason/:entityId` | `MageOS_RMA::rma_reason` |
| `DELETE` | `/V1/rma/reason/:entityId` | `MageOS_RMA::rma_reason` |
| `GET` | `/V1/rma/reason/search` | `MageOS_RMA::rma_reason` |

#### Resolution Types

| Method | Endpoint | ACL |
|---|---|---|
| `GET` | `/V1/rma/resolution-type/:entityId` | `MageOS_RMA::rma_resolution_type` |
| `POST` | `/V1/rma/resolution-type` | `MageOS_RMA::rma_resolution_type` |
| `PUT` | `/V1/rma/resolution-type/:entityId` | `MageOS_RMA::rma_resolution_type` |
| `DELETE` | `/V1/rma/resolution-type/:entityId` | `MageOS_RMA::rma_resolution_type` |
| `GET` | `/V1/rma/resolution-type/search` | `MageOS_RMA::rma_resolution_type` |

#### Item Conditions

| Method | Endpoint | ACL |
|---|---|---|
| `GET` | `/V1/rma/item-condition/:entityId` | `MageOS_RMA::rma_item_condition` |
| `POST` | `/V1/rma/item-condition` | `MageOS_RMA::rma_item_condition` |
| `PUT` | `/V1/rma/item-condition/:entityId` | `MageOS_RMA::rma_item_condition` |
| `DELETE` | `/V1/rma/item-condition/:entityId` | `MageOS_RMA::rma_item_condition` |
| `GET` | `/V1/rma/item-condition/search` | `MageOS_RMA::rma_item_condition` |

## GraphQL API

The module provides GraphQL queries and mutations for headless frontend integration.

### Queries

#### customerReturns

Returns a paginated list of returns for the logged-in customer. Requires customer token.

```graphql
query {
    customerReturns(pageSize: 10, currentPage: 1) {
        items {
            rma_id
            increment_id
            order_number
            status { code label }
            created_at
        }
        total_count
        page_info { page_size current_page total_pages }
    }
}
```

#### customerReturn

Returns a single return by ID for the logged-in customer. Requires customer token.

```graphql
query {
    customerReturn(rma_id: 1) {
        rma_id
        increment_id
        order_number
        status { id code label }
        reason { id code label }
        resolution_type { id code label }
        items {
            product_name
            product_sku
            qty_requested
            condition { code label }
        }
        comments {
            comment_id
            author_type
            author_name
            comment
            created_at
        }
    }
}
```

#### guestReturn

Returns a single return for a guest order, authenticated by order number and email.

```graphql
query {
    guestReturn(order_number: "000000001", email: "guest@example.com", rma_id: 1) {
        rma_id
        increment_id
        status { code label }
        items { product_name qty_requested }
    }
}
```

#### returnComments

Returns a paginated list of customer-visible comments for a return. Requires customer token.

```graphql
query {
    returnComments(rma_id: 1, pageSize: 50, currentPage: 1) {
        items {
            comment_id
            author_type
            author_name
            comment
            created_at
        }
        total_count
    }
}
```

### Mutations

#### createCustomerReturn

Creates a return for a customer order. Requires customer token.

```graphql
mutation {
    createCustomerReturn(input: {
        order_id: 1
        reason_id: 1
        resolution_type_id: 1
        items: [
            { order_item_id: 1, qty_requested: 1, condition_id: 1 }
        ]
    }) {
        return {
            rma_id
            increment_id
            status { code label }
        }
    }
}
```

#### createGuestReturn

Creates a return for a guest order, authenticated by order number and email.

```graphql
mutation {
    createGuestReturn(input: {
        order_number: "000000001"
        email: "guest@example.com"
        reason_id: 1
        resolution_type_id: 1
        items: [
            { order_item_id: 1, qty_requested: 1, condition_id: 1 }
        ]
    }) {
        return {
            rma_id
            increment_id
            status { code label }
        }
    }
}
```

#### addReturnComment

Adds a comment to a return. Requires customer token.

```graphql
mutation {
    addReturnComment(input: {
        rma_id: 1
        comment: "When will my refund be processed?"
    }) {
        comment_id
        author_type
        author_name
        comment
        created_at
    }
}
```

### Authentication

| Operation | Auth |
|---|---|
| `customerReturns`, `customerReturn`, `returnComments`, `addReturnComment` | Customer token (`Authorization: Bearer <customer_token>`) |
| `createCustomerReturn` | Customer token — ownership of the order is verified |
| `guestReturn`, `createGuestReturn` | No token — authenticated by `order_number` + `email` |

### Types

All lookup fields (status, reason, resolution_type, condition) return a `ReturnLookupValue` with `id`, `code` and `label`. The `label` is store-localized based on the current store view header (`Store`).

## Hyvä Theme Compatibility

This module is **Hyvä-ready** out of the box. It includes both Luma (default) and Hyvä templates within the same module — no separate compatibility module is needed.

### How it works

The module ships `hyva_` prefixed layout XML files alongside the standard Luma layout files. When running under a Hyvä theme, Magento automatically processes these `hyva_*` handles **after** the regular handles. The Hyvä layouts remove the Luma blocks and replace them with blocks pointing to Alpine.js + Tailwind CSS templates.

### What's included

| Luma layout | Hyvä layout | Purpose |
|---|---|---|
| `customer_account.xml` | `hyva_customer_account.xml` | "My Returns" sidebar link |
| `rma_customer_history.xml` | `hyva_rma_customer_history.xml` | Returns list page |
| `rma_customer_view.xml` | `hyva_rma_customer_view.xml` | Return detail + comments |
| `rma_customer_create.xml` | `hyva_rma_customer_create.xml` | Create return form |
| `rma_guest_create.xml` | `hyva_rma_guest_create.xml` | Guest return form |
| `sales_order_view.xml` | `hyva_sales_order_view.xml` | "Request Return" button (customer) |
| `sales_guest_view.xml` | `hyva_sales_guest_view.xml` | "Request Return" button (guest) |

### Templates

Hyvä templates are located under `view/frontend/templates/hyva/` and use:

- **Alpine.js** for all interactive behavior (AJAX item loading, comments polling, form validation)
- **Tailwind CSS** utility classes for styling
- **Native `fetch()`** instead of jQuery `$.ajax()`
- **`hyva.getCookie('form_key')`** for CSRF token retrieval instead of jQuery Cookie

The comments system implements the same smart polling mechanism as the Luma version (10s → 30s → 60s backoff, Visibility API pause/resume) using plain Alpine.js.

### Tailwind Build

Hyvä automatically discovers and purges Tailwind classes from module templates. After installing or updating this module, rebuild Tailwind from your Hyvä theme:

```bash
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
```

## Status Codes

Status codes are defined as constants in `Model\RMA\StatusCodes`:

| Constant | Value |
|---|---|
| `NEW_REQUEST` | `new_request` |
| `NEED_DETAILS` | `need_details` |
| `APPROVED` | `approved` |
| `REJECTED` | `rejected` |
| `SHIPPED_BY_CUSTOMER` | `shipped_by_customer` |
| `RECEIVED_BY_ADMIN` | `received_by_admin` |
| `CANCELED_BY_CUSTOMER` | `canceled_by_customer` |
| `RESOLVED` | `resolved` |
