# Changelog

## [2.4.1] - 2026-07-23

### Fixed
- Support PHP 8.2 and declare an open Magento compatibility range by @marcelmtz in #47
- Use full qalified class names in API interfaces result types by @stollr in #49 and @Hawksama in #50

## [2.4.0] - 2026-07-05

### Added
- Guest-form RMA requests are now linked to the customer account when the order belongs to a registered customer, so they appear in "My Returns"

### Fixed
- **[i18n]** Lookup labels (status, reason, resolution type, item condition) now translated in transactional emails (store emulation in `Service\Email\Sender`) and frontend selects (`__()` in `AbstractLookupSource::toOptionArray()`); seed label translations added to all i18n CSVs
- **[Code Quality]** `TypeError` on repository `getList()` — dedicated SearchResults classes in `Model/Data/` wired via `di.xml` preferences

## [2.3.3] - 2026-06-22

### Fixed
- Add a new event (`rma_commit_after`) to use for sending new RMA emails. This fixes the issue #39

## [2.3.2] - 2026-06-18

### Fixed
- Shortened long DB constraint names to comply with database limitations

### Added
- **Romanian translations** for UI, emails, and configuration

## [2.3.1] - 2026-06-05
### Fixed
- Fix unqualified column reference in customer RMA list (#36)

## [2.3.0] - 2026-05-10

### Changed
- **Enable RMA** system configuration default switched from `Yes` to `No` at installation — RMA feature must now be explicitly enabled by the admin after install (#34)
- README updated to reflect the new `Enable RMA` default value (#34)

## [2.2.1] - 2026-04-27

### Fixed
- **[Code Quality]** Raw SQL `getConnection()->fetchOne()` in `RMARepository::save()` for old status detection replaced with repository pattern `$this->get()->getStatusId()` (#22)
- **[Code Quality]** N+1 query in customer RMA history — `ListRma::getOrderIncrementId()` calling `orderRepository->get()` per row replaced with single LEFT JOIN on `sales_order` via new opt-in `Collection::joinSalesOrder()` method (#24)
- **[Compatibility]** PHP 8.5: nullable getter return values cast to `(int)`/`(string)` before use as array keys in 5 admin DataProviders (`RMA`, `Status`, `Reason`, `ResolutionType`, `ItemCondition`), 3 UI listing columns (`RmaActions`, `StatusActions`, `GenericEntityActions`), and `ReturnDataProvider` GraphQL lookup — prevents null-as-array-key warnings on PHP 8.5+

## [2.2.0] - 2026-04-21

### Fixed
- **[Security]** Trailing slash gap on absolute attachment paths in `AttachmentService` allowed potential path-traversal edge cases (#32)

### Added
- Unit test coverage for `AbstractRepository`, `RMARepository`, `StatusResolver`, `OrderEligibility`, `RmaSubmitService`, `AttachmentService`, attachment upload, and customer attachment download (#32)

## [2.1.0] - 2026-04-15

### Fixed
- **[Security]** Allowed file extensions config switched from free-form comma list to multiselect with server-side mimetype validation in `AttachmentService` (#30)

### Added
- `Model/Config/Source/AllowedExtensions.php` source model backing the new multiselect config (#30)

## [2.0.1] - 2026-04-09

### Fixed
- `NoSuchEntityException` resolved in wrong namespace in `Block/Customer/Rma/ListRma.php` — catch block never matched
- `SearchCriteriaBuilder::addSortOrder()` called with two strings instead of `SortOrder` object in `AbstractRmaManagement` — runtime error when sorting items/comments via management layer

## [2.0.0] - 2026-04-08

### Breaking Changes
- `RmaSubmitService::saveItems()` now requires `OrderInterface $order` as third parameter
- `AbstractRmaManagement` now injects `SearchCriteriaBuilderFactory` instead of `SearchCriteriaBuilder` (affects `RmaItemManagement` and `RmaCommentManagement` constructors)

### Fixed
- **[Security]** Order item IDs not validated against submitted order (#6)
- **[Security]** No quantity validation against available qty — customers could request more than ordered (#7)
- **[Data Integrity]** RMA creation not transactional — partial saves on failure (#10)
- **[Data Integrity]** Store view deletion cascading to RMA records — changed FK to SET NULL (#11)
- **[Data Integrity]** `db_schema_whitelist.json` missing `rma_attachment` table and indexes for `reason_id`/`resolution_type_id` (#12)
- **[Infrastructure]** `AutoApproveRma` observer causing redundant double-save — removed, service already handles it (#15)
- **[Code Quality]** `SearchCriteriaBuilder` shared instance mutated across calls in `AbstractRmaManagement` (#23)
- **[Code Quality]** Frontend Luma templates using inline styles — extracted to LESS classes (#25)
- **[API]** `getStoreLabels()` return type `array` not Web API serializable — typed as `string[]` (#21)
- **[i18n]** 65 missing translation strings added to all 4 locale CSVs (#27)

### Added
- `@api` annotation on all 27 interfaces (#16)
- Sequence dependencies in `module.xml` for `Magento_Sales`, `Magento_Webapi`, `Magento_GraphQl` (#13)
- Composer `require` for `magento/module-sales`, `magento/module-webapi`, `magento/module-graph-ql` (#13)
- Indexes on `rma_entity.reason_id` and `rma_entity.resolution_type_id` (#12)
- Sub-resource pattern documented in README for items and comments endpoints (#19)

### Removed
- `Observer/AutoApproveRma.php` and its event binding in `events.xml` (#15)

## [1.3.1] - 2026-03-09

### Fixed
- JSON responses returning objects instead of arrays due to `array_map` preserving entity ID keys from `$collection->getItems()` — caused admin comment polling to silently fail and Hyvä comment save to throw TypeError on `forEach`
- Comment save controllers now wrap everything in a catch-all `Exception` handler to guarantee JSON response even on unexpected errors
- Comment attachments not appearing in real-time — `toArray()` was called before `saveFromJson()`, so attachments were not yet in DB when queried
- Guest RMA creation not passing `attachmentsJson` to `createRma()` — caused silent data loss for guest attachments

### Added
- Accepted file types info (extensions, max size, max files) displayed in comment upload dropzones (Luma, Hyvä, Admin)

## [1.3.0] - 2026-03-08

### Changed

#### Controller Deduplication (~500 lines removed)
- New abstract controllers: `AbstractLookupSave`, `AbstractLookupDelete`, `AbstractLookupInlineEdit`, `AbstractLookupMassDelete`, `AbstractLookupEdit`
- 20 concrete lookup controllers (Status, Reason, ResolutionType, ItemCondition × 5 actions) reduced to minimal subclasses

#### Security
- Path traversal protection in `AttachmentService::getAbsolutePath()` — validates resolved path starts with base attachments directory
- Filename sanitization in `moveFromTmpAndSave()` via `basename()`
- File size validation before and after upload in `uploadToTmp()`
- Inline edit mass assignment protection via field whitelists in abstract controller

#### Code Quality
- `foreach` loops replaced with `array_map`/`array_filter` across 6 locations
- `AbstractHelper` removed from `ModuleConfig` — now injects `ScopeConfigInterface` directly
- Unused `LoggerInterface` removed from `AttachmentService`
- `json_encode`/`json_decode` replaced with `Magento\Framework\Serialize\Serializer\Json`
- Download logic deduplicated into `AttachmentService::createDownloadResponse()`
- Status label resolution moved inside `Sender::sendCustomerStatusChangeEmail()`
- Double order load eliminated in `ReturnDataProvider`
- Comment save error handling split: comment save and attachment save in separate try/catch blocks
- Hyvä Alpine `submitComment()` now has proper error handling (catch + else branches)
- FQCN inline references replaced with `use` imports
- Coding style fixes: spacing, import ordering, redundant annotations removed
- `$block->escape*()` migrated to `$escaper->escape*()` in all 13 PHTML templates
- FK validation added for status_id, reason_id, resolution_type_id in RMA save controllers
- FilterGroup OR semantics preserved in `AbstractRmaManagement::buildScopedSearchCriteria()`

### Added
- `@throws` annotations on all methods that can throw (direct or indirect)
- `HttpPostActionInterface` on `AbstractLookupInlineEdit`
- Magic number constants `BYTES_PER_KB`, `BYTES_PER_MB` in `AttachmentService`
- New i18n strings for error messages (4 languages)

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
