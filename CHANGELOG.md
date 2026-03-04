# Changelog

## [1.2.0] - 2026-03-04

### Removed
- 5 `NewAction` controllers (Rma, Status, Reason, ResolutionType, ItemCondition) — redundant `forward('edit')`, listing buttons now point directly to `*/*/edit`
- 3 entity-specific Actions columns (ReasonActions, ResolutionTypeActions, ItemConditionActions) — replaced by `GenericEntityActions` with di.xml virtual types

### Added
- `GenericEntityActions` UI component column with parameterized `editUrlPath`, `deleteUrlPath`, `entityLabel` — reused via 3 virtual types in di.xml

### Fixed
- `$storeId` undefined in `Service\Email\Sender::sendRmaEmail()` — now correctly resolved from `$rma->getStoreId()`

### Changed
- All constants switched to unqualified `const` with explicit type hints (`const string`, `const array`) across the entire module
- `CleanupCommand::getClosedStatusIds()` condensed from foreach to `array_map`
- `StatusCommand::renderItems()` inlined intermediate `$conditionLabel` variable
- Removed 2 unused imports (`ItemInterface` in OrderItems block, `SearchResultsInterface` in AbstractRmaManagement)

## [1.1.0] - 2026-02-22

### Added

#### File Attachments
- File upload support at RMA creation (drag & drop or file picker) for both customer and guest
- File upload support in comments (admin and customer)
- Unified **Attachments** section on RMA detail page showing all attachments regardless of source (creation or comment)
- Attachments remain visible inline within each comment for context
- Dynamic update of unified section when new comments with attachments arrive via AJAX polling
- Admin can delete attachments from both the unified section and comment timeline (synchronized)
- Dedicated download controller with authorization checks (customer ownership, admin ACL)
- Temporary upload endpoint with server-side validation (extension, file size)
- Configurable allowed file extensions (default: jpg, jpeg, png, gif, webp, mp4, mov, pdf, doc, docx, zip)
- Configurable maximum file size per file (default: 10 MB)
- Configurable maximum files per upload (default: 5)
- Configuration available at Stores > Configuration > Sales > RMA - Return Management > Attachments
- Full Hyvä support with Alpine.js upload widget and dynamic unified section
- Shared JS utilities (`rma-utils.js`, `rma-file-upload.js`) for Luma frontend and admin
- Attachment translations for it_IT, fr_FR, de_DE, es_ES (24 new strings per language)
- `AttachmentService` with `saveFromJson()`, `moveFromTmpAndSave()`, `getByRmaId()`, `toArray()`, `formatFileSize()`
- `AttachmentConfigTrait` for shared configuration getters across blocks
- Database table `rma_attachment` with fields: entity_id, rma_entity_id, comment_id (nullable), file_name, file_path, file_size

## [1.0.2] - 2026-02-20
### Fixed
- Added PHP version constraint to composer.json to reflect requirements

## [1.0.1] - 2026-02-15

### Fixed
- Admin mass delete not working on all grids (RMA, Status, Reason, Resolution Type, Item Condition) — added missing `$_idFieldName` to all collections so `Document::getId()` resolves correctly in `Filter::getFilterIds()`
- Multi-store order eligibility — `OrderEligibility::getCustomerEligibleOrders()` now filters by all store IDs within the same website instead of exact store ID

## [1.0.0] - 2026-02-13

### Added

#### Core RMA Management
- Full RMA entity with auto-generated increment IDs (configurable prefix)
- RMA items linked to original order items with quantity tracking (requested, approved, returned)
- Chat-like comments system with real-time polling (smart backoff: 10s, 30s, 60s) and Page Visibility API
- Configurable return eligibility period (days) and allowed order statuses

#### Lookup Entities
- Status management with protected system statuses (`new_request`, `approved`, `rejected`, `items_received`, `closed`)
- Reason management (default: Defective, Wrong Item, Changed Mind, Not as Described)
- Resolution Type management (default: Refund, Replacement, Store Credit)
- Item Condition management (default: Unopened, Opened, Damaged)
- Multi-store label support for all lookup entities

#### Admin Panel
- RMA request grid with filtering, sorting, inline editing, and mass delete
- RMA edit form with status management and admin comments
- RMA creation form with order search, item selection, and quantity picker
- Full CRUD grids and forms for Status, Reason, Resolution Type, and Item Condition
- Inline editing support on all lookup grids
- ACL resources: `rma_manage`, `rma_status`, `rma_reason`, `rma_resolution_type`, `rma_item_condition`

#### Customer Frontend
- "My Returns" section in customer account with paginated history
- RMA detail view with item list, status, and chat comments
- RMA creation form with dynamic order item loading (AJAX)
- "Request Return" link on order view page

#### Guest Support
- Guest RMA creation via order number + email lookup
- "Request Return" link on guest order view page

#### REST API
- Full CRUD endpoints for RMA entity (`/V1/rma`)
- RMA search by increment ID (`/V1/rma/increment-id/:incrementId`)
- RMA item management (`/V1/rma/:rmaId/items`)
- RMA comment management (`/V1/rma/:rmaId/comments`)
- Full CRUD + search for Status, Reason, Resolution Type, and Item Condition
- 30+ REST API endpoints total

#### GraphQL API
- `customerReturns` query with pagination
- `customerReturn` query for single RMA detail
- `guestReturn` query for guest order returns
- `returnComments` query for RMA chat history
- `createCustomerReturn` mutation
- `createGuestReturn` mutation
- `addReturnComment` mutation

#### Email Notifications
- Customer email on new RMA creation
- Customer email on RMA status change
- Admin email on new RMA creation
- Configurable sender identity and admin notification address

#### CLI Commands
- `bin/magento rma:list` - List RMA requests with filters
- `bin/magento rma:status` - Show RMA status details
- `bin/magento rma:cleanup` - Clean up old closed RMAs

#### System Configuration
- Enable/disable RMA module
- Increment ID prefix
- Return period (days)
- Auto-approve toggle
- Allowed order statuses for returns
- Email sender, templates, and admin notification address

#### Internationalization
- Italian (it_IT)
- French (fr_FR)
- German (de_DE)
- Spanish (es_ES)

#### Hyva Theme Compatibility
- Alpine.js + Tailwind CSS templates for all customer-facing pages
- Hyva-prefixed layout XML files with automatic template discovery
- Smart comment polling with `hyva.getCookie('form_key')` for CSRF

#### Architecture
- Service layer: `OrderEligibility`, `RmaSubmitService`, `GuestOrderService`, `LabelResolver`, `CommentFormatter`
- Abstract base repository (`AbstractRepository`) for all lookup entity repositories
- Abstract admin controller (`AbstractLookupController`) for lookup entity management
- Generic admin button blocks (`GenericBackButton`, `GenericSaveButton`, `GenericDeleteButton`) with di.xml virtual types
- Event-driven architecture: `rma_created_after`, `rma_status_change_after`, semantic status events
- Data patches for default statuses, reasons, resolution types, and item conditions
- Shared JS in `view/base/web/js/` for admin and frontend comment systems
