# Xboard API Documentation

This document provides a comprehensive overview of all API endpoints available in the Xboard system, organized by access level.

## API Base URLs

- **V1 API**: `/api/v1/`
- **V2 API**: `/api/v2/`

## Authentication

- **Guest**: No authentication required
- **User**: Requires user authentication (`user` middleware)
- **Staff**: Requires staff privileges (`staff` middleware)
- **Admin**: Requires admin privileges (`admin` middleware)
- **Client**: Requires client authentication (`client` middleware)
- **Server**: Requires server authentication (`server` middleware)

---

## 🌐 Guest APIs (Public - No Authentication Required)

### Plans
- **GET** `/api/v1/guest/plan/fetch`
  - **Purpose**: Get all available subscription plans for public viewing
  - **Returns**: List of available plans with pricing, features, and limits
  - **Data**: Plan ID, name, prices (monthly/quarterly/yearly), transfer limits, speed limits, device limits, capacity info

### Configuration
- **GET** `/api/v1/guest/comm/config`
  - **Purpose**: Get public configuration settings
  - **Returns**: Public app configuration
  - **Data**: ToS URL, email verification settings, invite requirements, reCAPTCHA settings, app description, app URL, logo

### Payment Webhooks
- **GET/POST** `/api/v1/guest/payment/notify/{method}/{uuid}`
  - **Purpose**: Handle payment notifications from payment providers
  - **Returns**: Payment processing status
  - **Data**: Payment confirmation and processing results

### Telegram Webhooks
- **POST** `/api/v1/guest/telegram/webhook`
  - **Purpose**: Handle Telegram bot webhook events
  - **Returns**: Webhook processing status
  - **Data**: Telegram bot event processing results

---

## 🔐 Authentication APIs (Passport)

### V1 Authentication
- **POST** `/api/v1/passport/auth/register`
  - **Purpose**: User registration
  - **Returns**: Registration success/failure
  - **Data**: User account creation status

- **POST** `/api/v1/passport/auth/login`
  - **Purpose**: User login
  - **Returns**: Authentication token and user info
  - **Data**: JWT token, user details, session info

- **GET** `/api/v1/passport/auth/token2Login`
  - **Purpose**: Token-based login
  - **Returns**: Login status
  - **Data**: Authentication status

- **POST** `/api/v1/passport/auth/forget`
  - **Purpose**: Password reset request
  - **Returns**: Reset email status
  - **Data**: Password reset confirmation

- **POST** `/api/v1/passport/auth/getQuickLoginUrl`
  - **Purpose**: Generate quick login URL
  - **Returns**: Quick login URL
  - **Data**: Temporary login URL

- **POST** `/api/v1/passport/auth/loginWithMailLink`
  - **Purpose**: Login via email link
  - **Returns**: Login status
  - **Data**: Authentication confirmation

### Communication
- **POST** `/api/v1/passport/comm/sendEmailVerify`
  - **Purpose**: Send email verification
  - **Returns**: Email sending status
  - **Data**: Verification email delivery confirmation

- **POST** `/api/v1/passport/comm/pv`
  - **Purpose**: Page view tracking
  - **Returns**: Tracking status
  - **Data**: Analytics tracking confirmation

### V2 Authentication
Same endpoints as V1 but under `/api/v2/passport/` prefix.

---

## 👤 User APIs (Authenticated Users)

### User Management
- **GET** `/api/v1/user/info`
  - **Purpose**: Get current user information
  - **Returns**: User profile data
  - **Data**: Email, transfer limits, login history, subscription status, balance, commission info, telegram ID, avatar URL

- **POST** `/api/v1/user/update`
  - **Purpose**: Update user preferences
  - **Returns**: Update status
  - **Data**: Updated user preferences (reminders, etc.)

- **POST** `/api/v1/user/changePassword`
  - **Purpose**: Change user password
  - **Returns**: Password change status
  - **Data**: Password update confirmation

- **GET** `/api/v1/user/resetSecurity`
  - **Purpose**: Reset security credentials (UUID, token)
  - **Returns**: New subscribe URL
  - **Data**: New subscription URL with updated token

- **GET** `/api/v1/user/checkLogin`
  - **Purpose**: Check login status
  - **Returns**: Login status and permissions
  - **Data**: Login status, admin privileges

- **GET** `/api/v1/user/getStat`
  - **Purpose**: Get user statistics
  - **Returns**: User statistics summary
  - **Data**: Pending orders count, open tickets count, invited users count

- **GET** `/api/v1/user/getSubscribe`
  - **Purpose**: Get subscription information
  - **Returns**: Subscription details and URL
  - **Data**: Plan details, subscription URL, usage stats, reset schedule

- **POST** `/api/v1/user/transfer`
  - **Purpose**: Transfer commission to balance
  - **Returns**: Transfer status
  - **Data**: Transfer confirmation and updated balances

- **POST** `/api/v1/user/getQuickLoginUrl`
  - **Purpose**: Generate quick login URL
  - **Returns**: Quick login URL
  - **Data**: Temporary login URL

### Session Management
- **GET** `/api/v1/user/getActiveSession`
  - **Purpose**: Get active sessions
  - **Returns**: List of active sessions
  - **Data**: Session details, login times, IP addresses

- **POST** `/api/v1/user/removeActiveSession`
  - **Purpose**: Remove specific session
  - **Returns**: Session removal status
  - **Data**: Session termination confirmation

### Orders & Billing
- **POST** `/api/v1/user/order/save`
  - **Purpose**: Create new order
  - **Returns**: Order creation status
  - **Data**: Order details, payment information

- **POST** `/api/v1/user/order/checkout`
  - **Purpose**: Checkout order
  - **Returns**: Payment processing info
  - **Data**: Payment URL, order status

- **GET** `/api/v1/user/order/check`
  - **Purpose**: Check order status
  - **Returns**: Order status
  - **Data**: Order processing status

- **GET** `/api/v1/user/order/detail`
  - **Purpose**: Get order details
  - **Returns**: Detailed order information
  - **Data**: Order items, pricing, status, payment info

- **GET** `/api/v1/user/order/fetch`
  - **Purpose**: Get user's orders
  - **Returns**: List of user orders
  - **Data**: Order history with status and details

- **GET** `/api/v1/user/order/getPaymentMethod`
  - **Purpose**: Get available payment methods
  - **Returns**: List of payment options
  - **Data**: Payment providers, fees, availability

- **POST** `/api/v1/user/order/cancel`
  - **Purpose**: Cancel order
  - **Returns**: Cancellation status
  - **Data**: Order cancellation confirmation

### Plans
- **GET** `/api/v1/user/plan/fetch`
  - **Purpose**: Get available plans for authenticated user
  - **Returns**: List of plans with user-specific availability
  - **Data**: Plans with pricing, user eligibility, renewal options

### Invitations
- **GET** `/api/v1/user/invite/save`
  - **Purpose**: Generate invitation code
  - **Returns**: Invitation code
  - **Data**: Invitation code and sharing info

- **GET** `/api/v1/user/invite/fetch`
  - **Purpose**: Get invitation statistics
  - **Returns**: Invitation data
  - **Data**: Invitation codes, usage stats, commissions

- **GET** `/api/v1/user/invite/details`
  - **Purpose**: Get detailed invitation information
  - **Returns**: Invitation details
  - **Data**: Detailed invitation statistics and earnings

### Support & Communication
- **GET** `/api/v1/user/notice/fetch`
  - **Purpose**: Get user notices
  - **Returns**: List of notices
  - **Data**: System announcements, updates, alerts

- **POST** `/api/v1/user/ticket/save`
  - **Purpose**: Create support ticket
  - **Returns**: Ticket creation status
  - **Data**: Ticket ID and details

- **GET** `/api/v1/user/ticket/fetch`
  - **Purpose**: Get user's tickets
  - **Returns**: List of support tickets
  - **Data**: Ticket history, status, responses

- **POST** `/api/v1/user/ticket/reply`
  - **Purpose**: Reply to ticket
  - **Returns**: Reply status
  - **Data**: Reply confirmation

- **POST** `/api/v1/user/ticket/close`
  - **Purpose**: Close ticket
  - **Returns**: Closure status
  - **Data**: Ticket closure confirmation

- **POST** `/api/v1/user/ticket/withdraw`
  - **Purpose**: Withdraw ticket
  - **Returns**: Withdrawal status
  - **Data**: Ticket withdrawal confirmation

### Servers
- **GET** `/api/v1/user/server/fetch`
  - **Purpose**: Get available servers
  - **Returns**: List of servers user can access
  - **Data**: Server details, locations, status, protocols

### Coupons
- **POST** `/api/v1/user/coupon/check`
  - **Purpose**: Validate coupon code
  - **Returns**: Coupon validity and discount info
  - **Data**: Coupon details, discount amount, validity

### Telegram Integration
- **GET** `/api/v1/user/telegram/getBotInfo`
  - **Purpose**: Get Telegram bot information
  - **Returns**: Bot connection info
  - **Data**: Bot details, connection status

### Configuration
- **GET** `/api/v1/user/comm/config`
  - **Purpose**: Get user-specific configuration
  - **Returns**: User configuration settings
  - **Data**: User preferences, feature availability

- **POST** `/api/v1/user/comm/getStripePublicKey`
  - **Purpose**: Get Stripe public key for payments
  - **Returns**: Stripe configuration
  - **Data**: Stripe public key for payment processing

### Knowledge Base
- **GET** `/api/v1/user/knowledge/fetch`
  - **Purpose**: Get knowledge base articles
  - **Returns**: List of help articles
  - **Data**: Articles, categories, content

- **GET** `/api/v1/user/knowledge/getCategory`
  - **Purpose**: Get knowledge base categories
  - **Returns**: List of categories
  - **Data**: Category structure and organization

### Statistics
- **GET** `/api/v1/user/stat/getTrafficLog`
  - **Purpose**: Get traffic usage logs
  - **Returns**: Traffic usage history
  - **Data**: Traffic logs, usage patterns, timestamps

### V2 User APIs
- **GET** `/api/v2/user/resetSecurity`
  - **Purpose**: Reset security credentials
  - **Returns**: New security credentials
  - **Data**: Updated security tokens

- **GET** `/api/v2/user/info`
  - **Purpose**: Get user information (V2)
  - **Returns**: User profile data
  - **Data**: Enhanced user profile information

---

## 📱 Client APIs (Client Authentication)

### Subscription
- **GET** `/api/v1/client/subscribe`
  - **Purpose**: Get subscription configuration
  - **Returns**: Client configuration for VPN apps
  - **Data**: Server configurations, protocols, connection details

### App Configuration
- **GET** `/api/v1/client/app/getConfig`
  - **Purpose**: Get app configuration
  - **Returns**: App configuration settings
  - **Data**: App settings, features, URLs

- **GET** `/api/v1/client/app/getVersion`
  - **Purpose**: Get app version information
  - **Returns**: Version details
  - **Data**: Current version, update availability

---

## 🖥️ Server APIs (Server Authentication)

### UniProxy
- **GET** `/api/v1/server/UniProxy/config`
  - **Purpose**: Get server configuration
  - **Returns**: Server configuration
  - **Data**: Server settings and parameters

- **GET** `/api/v1/server/UniProxy/user`
  - **Purpose**: Get user data for server
  - **Returns**: User information for server
  - **Data**: User access rights, quotas, settings

- **POST** `/api/v1/server/UniProxy/push`
  - **Purpose**: Push data to server
  - **Returns**: Push status
  - **Data**: Data synchronization confirmation

- **POST** `/api/v1/server/UniProxy/alive`
  - **Purpose**: Server heartbeat
  - **Returns**: Alive status
  - **Data**: Server health status

- **GET** `/api/v1/server/UniProxy/alivelist`
  - **Purpose**: Get alive servers list
  - **Returns**: List of active servers
  - **Data**: Server status and availability

- **POST** `/api/v1/server/UniProxy/status`
  - **Purpose**: Update server status
  - **Returns**: Status update confirmation
  - **Data**: Server status update

### Shadowsocks Tidalab
- **GET** `/api/v1/server/ShadowsocksTidalab/user`
  - **Purpose**: Get user data for Shadowsocks
  - **Returns**: Shadowsocks user configuration
  - **Data**: Shadowsocks-specific user settings

- **POST** `/api/v1/server/ShadowsocksTidalab/submit`
  - **Purpose**: Submit Shadowsocks data
  - **Returns**: Submission status
  - **Data**: Data submission confirmation

### Trojan Tidalab
- **GET** `/api/v1/server/TrojanTidalab/config`
  - **Purpose**: Get Trojan server configuration
  - **Returns**: Trojan configuration
  - **Data**: Trojan server settings

- **GET** `/api/v1/server/TrojanTidalab/user`
  - **Purpose**: Get user data for Trojan
  - **Returns**: Trojan user configuration
  - **Data**: Trojan-specific user settings

- **POST** `/api/v1/server/TrojanTidalab/submit`
  - **Purpose**: Submit Trojan data
  - **Returns**: Submission status
  - **Data**: Data submission confirmation

---

## 👨‍💼 Staff APIs (Staff Authentication)

**Note**: Staff functionality exists but appears to be integrated into admin routes rather than having dedicated staff routes. Staff users have `is_staff` flag and can access certain admin functions with limited permissions.

### User Management (Staff Level)
- Staff can manage non-admin, non-staff users
- Limited user update capabilities
- Send emails to users
- Ban users
- Access user information

---

## 🔧 Admin APIs (Administrator Authentication)

### Configuration Management
- **GET** `/api/v2/{admin_path}/config/fetch`
  - **Purpose**: Get system configuration
  - **Returns**: Complete system settings
  - **Data**: All configuration parameters

- **POST** `/api/v2/{admin_path}/config/save`
  - **Purpose**: Save system configuration
  - **Returns**: Save status
  - **Data**: Configuration update confirmation

- **GET** `/api/v2/{admin_path}/config/getEmailTemplate`
  - **Purpose**: Get email templates
  - **Returns**: Email template configurations
  - **Data**: Email templates and settings

- **GET** `/api/v2/{admin_path}/config/getThemeTemplate`
  - **Purpose**: Get theme templates
  - **Returns**: Theme configurations
  - **Data**: Available themes and settings

- **POST** `/api/v2/{admin_path}/config/setTelegramWebhook`
  - **Purpose**: Configure Telegram webhook
  - **Returns**: Webhook setup status
  - **Data**: Telegram integration status

- **POST** `/api/v2/{admin_path}/config/testSendMail`
  - **Purpose**: Test email configuration
  - **Returns**: Email test results
  - **Data**: Email sending test status

### Plan Management
- **GET** `/api/v2/{admin_path}/plan/fetch`
  - **Purpose**: Get all plans (admin view)
  - **Returns**: Complete plan list with admin details
  - **Data**: All plans with user counts, revenue, admin settings

- **POST** `/api/v2/{admin_path}/plan/save`
  - **Purpose**: Create/update plan
  - **Returns**: Plan save status
  - **Data**: Plan creation/update confirmation

- **POST** `/api/v2/{admin_path}/plan/drop`
  - **Purpose**: Delete plan
  - **Returns**: Deletion status
  - **Data**: Plan deletion confirmation

- **POST** `/api/v2/{admin_path}/plan/update`
  - **Purpose**: Update plan details
  - **Returns**: Update status
  - **Data**: Plan update confirmation

- **POST** `/api/v2/{admin_path}/plan/sort`
  - **Purpose**: Reorder plans
  - **Returns**: Sort status
  - **Data**: Plan ordering confirmation

### Server Management
#### Server Groups
- **GET** `/api/v2/{admin_path}/server/group/fetch`
  - **Purpose**: Get server groups
  - **Returns**: List of server groups
  - **Data**: Group configurations and permissions

- **POST** `/api/v2/{admin_path}/server/group/save`
  - **Purpose**: Create/update server group
  - **Returns**: Group save status
  - **Data**: Group creation/update confirmation

- **POST** `/api/v2/{admin_path}/server/group/drop`
  - **Purpose**: Delete server group
  - **Returns**: Deletion status
  - **Data**: Group deletion confirmation

#### Server Routes
- **GET** `/api/v2/{admin_path}/server/route/fetch`
  - **Purpose**: Get server routes
  - **Returns**: List of server routes
  - **Data**: Route configurations and rules

- **POST** `/api/v2/{admin_path}/server/route/save`
  - **Purpose**: Create/update server route
  - **Returns**: Route save status
  - **Data**: Route creation/update confirmation

- **POST** `/api/v2/{admin_path}/server/route/drop`
  - **Purpose**: Delete server route
  - **Returns**: Deletion status
  - **Data**: Route deletion confirmation

#### Server Management
- **GET** `/api/v2/{admin_path}/server/manage/getNodes`
  - **Purpose**: Get server nodes
  - **Returns**: List of server nodes
  - **Data**: Node details, status, configuration

- **POST** `/api/v2/{admin_path}/server/manage/update`
  - **Purpose**: Update server
  - **Returns**: Update status
  - **Data**: Server update confirmation

- **POST** `/api/v2/{admin_path}/server/manage/save`
  - **Purpose**: Create server
  - **Returns**: Creation status
  - **Data**: Server creation confirmation

- **POST** `/api/v2/{admin_path}/server/manage/drop`
  - **Purpose**: Delete server
  - **Returns**: Deletion status
  - **Data**: Server deletion confirmation

- **POST** `/api/v2/{admin_path}/server/manage/copy`
  - **Purpose**: Copy server configuration
  - **Returns**: Copy status
  - **Data**: Server copy confirmation

- **POST** `/api/v2/{admin_path}/server/manage/sort`
  - **Purpose**: Reorder servers
  - **Returns**: Sort status
  - **Data**: Server ordering confirmation

### Order Management
- **GET/POST** `/api/v2/{admin_path}/order/fetch`
  - **Purpose**: Get orders with filtering
  - **Returns**: Paginated order list
  - **Data**: Order details, user info, payment status

- **POST** `/api/v2/{admin_path}/order/update`
  - **Purpose**: Update order
  - **Returns**: Update status
  - **Data**: Order update confirmation

- **POST** `/api/v2/{admin_path}/order/assign`
  - **Purpose**: Assign order to plan
  - **Returns**: Assignment status
  - **Data**: Order assignment confirmation

- **POST** `/api/v2/{admin_path}/order/paid`
  - **Purpose**: Mark order as paid
  - **Returns**: Payment status
  - **Data**: Payment confirmation

- **POST** `/api/v2/{admin_path}/order/cancel`
  - **Purpose**: Cancel order
  - **Returns**: Cancellation status
  - **Data**: Order cancellation confirmation

- **POST** `/api/v2/{admin_path}/order/detail`
  - **Purpose**: Get order details
  - **Returns**: Detailed order information
  - **Data**: Complete order information

### User Management
- **GET/POST** `/api/v2/{admin_path}/user/fetch`
  - **Purpose**: Get users with filtering and pagination
  - **Returns**: Paginated user list
  - **Data**: User details, subscription info, usage stats

- **POST** `/api/v2/{admin_path}/user/update`
  - **Purpose**: Update user
  - **Returns**: Update status
  - **Data**: User update confirmation

- **GET** `/api/v2/{admin_path}/user/getUserInfoById`
  - **Purpose**: Get specific user info
  - **Returns**: User details
  - **Data**: Complete user information

- **POST** `/api/v2/{admin_path}/user/generate`
  - **Purpose**: Generate user account
  - **Returns**: Generation status
  - **Data**: New user account details

- **POST** `/api/v2/{admin_path}/user/dumpCSV`
  - **Purpose**: Export users to CSV
  - **Returns**: CSV export
  - **Data**: User data in CSV format

- **POST** `/api/v2/{admin_path}/user/sendMail`
  - **Purpose**: Send email to users
  - **Returns**: Email sending status
  - **Data**: Email delivery confirmation

- **POST** `/api/v2/{admin_path}/user/ban`
  - **Purpose**: Ban users
  - **Returns**: Ban status
  - **Data**: User ban confirmation

- **POST** `/api/v2/{admin_path}/user/resetSecret`
  - **Purpose**: Reset user secrets
  - **Returns**: Reset status
  - **Data**: Secret reset confirmation

- **POST** `/api/v2/{admin_path}/user/setInviteUser`
  - **Purpose**: Set invite relationships
  - **Returns**: Setting status
  - **Data**: Invite relationship confirmation

- **POST** `/api/v2/{admin_path}/user/destroy`
  - **Purpose**: Delete user
  - **Returns**: Deletion status
  - **Data**: User deletion confirmation

### Statistics & Analytics
- **GET** `/api/v2/{admin_path}/stat/getOverride`
  - **Purpose**: Get system overview
  - **Returns**: System statistics overview
  - **Data**: Key metrics, revenue, user counts

- **GET** `/api/v2/{admin_path}/stat/getStats`
  - **Purpose**: Get detailed statistics
  - **Returns**: Comprehensive statistics
  - **Data**: Revenue, usage, growth metrics

- **GET** `/api/v2/{admin_path}/stat/getServerLastRank`
  - **Purpose**: Get server performance ranking
  - **Returns**: Server ranking data
  - **Data**: Server performance metrics

- **GET** `/api/v2/{admin_path}/stat/getServerYesterdayRank`
  - **Purpose**: Get yesterday's server ranking
  - **Returns**: Historical server data
  - **Data**: Previous day server metrics

- **GET** `/api/v2/{admin_path}/stat/getOrder`
  - **Purpose**: Get order statistics
  - **Returns**: Order analytics
  - **Data**: Order trends, revenue data

- **GET/POST** `/api/v2/{admin_path}/stat/getStatUser`
  - **Purpose**: Get user statistics
  - **Returns**: User analytics
  - **Data**: User growth, activity metrics

- **GET** `/api/v2/{admin_path}/stat/getRanking`
  - **Purpose**: Get ranking data
  - **Returns**: Various rankings
  - **Data**: User, server, revenue rankings

- **GET** `/api/v2/{admin_path}/stat/getStatRecord`
  - **Purpose**: Get statistical records
  - **Returns**: Historical statistics
  - **Data**: Historical data records

- **GET** `/api/v2/{admin_path}/stat/getTrafficRank`
  - **Purpose**: Get traffic ranking
  - **Returns**: Traffic usage rankings
  - **Data**: Traffic usage by users/servers

### Notice Management
- **GET** `/api/v2/{admin_path}/notice/fetch`
  - **Purpose**: Get system notices
  - **Returns**: List of notices
  - **Data**: Notice content, visibility, scheduling

- **POST** `/api/v2/{admin_path}/notice/save`
  - **Purpose**: Create notice
  - **Returns**: Creation status
  - **Data**: Notice creation confirmation

- **POST** `/api/v2/{admin_path}/notice/update`
  - **Purpose**: Update notice
  - **Returns**: Update status
  - **Data**: Notice update confirmation

- **POST** `/api/v2/{admin_path}/notice/drop`
  - **Purpose**: Delete notice
  - **Returns**: Deletion status
  - **Data**: Notice deletion confirmation

- **POST** `/api/v2/{admin_path}/notice/show`
  - **Purpose**: Toggle notice visibility
  - **Returns**: Visibility status
  - **Data**: Notice visibility confirmation

- **POST** `/api/v2/{admin_path}/notice/sort`
  - **Purpose**: Reorder notices
  - **Returns**: Sort status
  - **Data**: Notice ordering confirmation

### Ticket Management
- **GET/POST** `/api/v2/{admin_path}/ticket/fetch`
  - **Purpose**: Get support tickets
  - **Returns**: Paginated ticket list
  - **Data**: Ticket details, user info, status

- **POST** `/api/v2/{admin_path}/ticket/reply`
  - **Purpose**: Reply to ticket
  - **Returns**: Reply status
  - **Data**: Ticket reply confirmation

- **POST** `/api/v2/{admin_path}/ticket/close`
  - **Purpose**: Close ticket
  - **Returns**: Closure status
  - **Data**: Ticket closure confirmation

### Coupon Management
- **GET/POST** `/api/v2/{admin_path}/coupon/fetch`
  - **Purpose**: Get coupons
  - **Returns**: List of coupons
  - **Data**: Coupon details, usage statistics

- **POST** `/api/v2/{admin_path}/coupon/generate`
  - **Purpose**: Generate coupons
  - **Returns**: Generation status
  - **Data**: New coupon codes

- **POST** `/api/v2/{admin_path}/coupon/drop`
  - **Purpose**: Delete coupon
  - **Returns**: Deletion status
  - **Data**: Coupon deletion confirmation

- **POST** `/api/v2/{admin_path}/coupon/show`
  - **Purpose**: Toggle coupon visibility
  - **Returns**: Visibility status
  - **Data**: Coupon visibility confirmation

- **POST** `/api/v2/{admin_path}/coupon/update`
  - **Purpose**: Update coupon
  - **Returns**: Update status
  - **Data**: Coupon update confirmation

### Knowledge Base Management
- **GET** `/api/v2/{admin_path}/knowledge/fetch`
  - **Purpose**: Get knowledge articles
  - **Returns**: List of articles
  - **Data**: Article content, categories, visibility

- **GET** `/api/v2/{admin_path}/knowledge/getCategory`
  - **Purpose**: Get knowledge categories
  - **Returns**: Category structure
  - **Data**: Category hierarchy and organization

- **POST** `/api/v2/{admin_path}/knowledge/save`
  - **Purpose**: Create/update article
  - **Returns**: Save status
  - **Data**: Article save confirmation

- **POST** `/api/v2/{admin_path}/knowledge/show`
  - **Purpose**: Toggle article visibility
  - **Returns**: Visibility status
  - **Data**: Article visibility confirmation

- **POST** `/api/v2/{admin_path}/knowledge/drop`
  - **Purpose**: Delete article
  - **Returns**: Deletion status
  - **Data**: Article deletion confirmation

- **POST** `/api/v2/{admin_path}/knowledge/sort`
  - **Purpose**: Reorder articles
  - **Returns**: Sort status
  - **Data**: Article ordering confirmation

### Payment Management
- **GET** `/api/v2/{admin_path}/payment/fetch`
  - **Purpose**: Get payment methods
  - **Returns**: List of payment providers
  - **Data**: Payment method configurations

- **GET** `/api/v2/{admin_path}/payment/getPaymentMethods`
  - **Purpose**: Get available payment methods
  - **Returns**: Payment method list
  - **Data**: Available payment options

- **POST** `/api/v2/{admin_path}/payment/getPaymentForm`
  - **Purpose**: Get payment form configuration
  - **Returns**: Form configuration
  - **Data**: Payment form settings

- **POST** `/api/v2/{admin_path}/payment/save`
  - **Purpose**: Save payment method
  - **Returns**: Save status
  - **Data**: Payment method save confirmation

- **POST** `/api/v2/{admin_path}/payment/drop`
  - **Purpose**: Delete payment method
  - **Returns**: Deletion status
  - **Data**: Payment method deletion confirmation

- **POST** `/api/v2/{admin_path}/payment/show`
  - **Purpose**: Toggle payment method visibility
  - **Returns**: Visibility status
  - **Data**: Payment method visibility confirmation

- **POST** `/api/v2/{admin_path}/payment/sort`
  - **Purpose**: Reorder payment methods
  - **Returns**: Sort status
  - **Data**: Payment method ordering confirmation

### System Management
- **GET** `/api/v2/{admin_path}/system/getSystemStatus`
  - **Purpose**: Get system status
  - **Returns**: System health information
  - **Data**: Server status, performance metrics

- **GET** `/api/v2/{admin_path}/system/getQueueStats`
  - **Purpose**: Get queue statistics
  - **Returns**: Queue performance data
  - **Data**: Queue metrics, job statistics

- **GET** `/api/v2/{admin_path}/system/getQueueWorkload`
  - **Purpose**: Get queue workload
  - **Returns**: Current queue workload
  - **Data**: Queue load and processing times

- **GET** `/api/v2/{admin_path}/system/getQueueMasters`
  - **Purpose**: Get queue masters (Horizon)
  - **Returns**: Queue master status
  - **Data**: Horizon supervisor information

- **GET** `/api/v2/{admin_path}/system/getSystemLog`
  - **Purpose**: Get system logs
  - **Returns**: System log entries
  - **Data**: Application logs, errors, events

- **GET** `/api/v2/{admin_path}/system/getHorizonFailedJobs`
  - **Purpose**: Get failed jobs
  - **Returns**: Failed job list
  - **Data**: Failed job details and errors

- **POST** `/api/v2/{admin_path}/system/clearSystemLog`
  - **Purpose**: Clear system logs
  - **Returns**: Clear status
  - **Data**: Log clearing confirmation

- **GET** `/api/v2/{admin_path}/system/getLogClearStats`
  - **Purpose**: Get log clearing statistics
  - **Returns**: Log management stats
  - **Data**: Log storage and clearing metrics

### Theme Management
- **GET** `/api/v2/{admin_path}/theme/getThemes`
  - **Purpose**: Get available themes
  - **Returns**: Theme list
  - **Data**: Theme details, configurations

- **POST** `/api/v2/{admin_path}/theme/upload`
  - **Purpose**: Upload theme
  - **Returns**: Upload status
  - **Data**: Theme upload confirmation

- **POST** `/api/v2/{admin_path}/theme/delete`
  - **Purpose**: Delete theme
  - **Returns**: Deletion status
  - **Data**: Theme deletion confirmation

- **POST** `/api/v2/{admin_path}/theme/saveThemeConfig`
  - **Purpose**: Save theme configuration
  - **Returns**: Save status
  - **Data**: Theme configuration save confirmation

- **POST** `/api/v2/{admin_path}/theme/getThemeConfig`
  - **Purpose**: Get theme configuration
  - **Returns**: Theme configuration
  - **Data**: Current theme settings

### Plugin Management
- **GET** `/api/v2/{admin_path}/plugin/getPlugins`
  - **Purpose**: Get installed plugins
  - **Returns**: Plugin list
  - **Data**: Plugin details, status, configuration

- **POST** `/api/v2/{admin_path}/plugin/upload`
  - **Purpose**: Upload plugin
  - **Returns**: Upload status
  - **Data**: Plugin upload confirmation

- **POST** `/api/v2/{admin_path}/plugin/delete`
  - **Purpose**: Delete plugin
  - **Returns**: Deletion status
  - **Data**: Plugin deletion confirmation

- **POST** `/api/v2/{admin_path}/plugin/install`
  - **Purpose**: Install plugin
  - **Returns**: Installation status
  - **Data**: Plugin installation confirmation

- **POST** `/api/v2/{admin_path}/plugin/uninstall`
  - **Purpose**: Uninstall plugin
  - **Returns**: Uninstallation status
  - **Data**: Plugin uninstallation confirmation

- **POST** `/api/v2/{admin_path}/plugin/enable`
  - **Purpose**: Enable plugin
  - **Returns**: Enable status
  - **Data**: Plugin enable confirmation

- **POST** `/api/v2/{admin_path}/plugin/disable`
  - **Purpose**: Disable plugin
  - **Returns**: Disable status
  - **Data**: Plugin disable confirmation

- **GET** `/api/v2/{admin_path}/plugin/config`
  - **Purpose**: Get plugin configuration
  - **Returns**: Plugin configuration
  - **Data**: Plugin settings

- **POST** `/api/v2/{admin_path}/plugin/config`
  - **Purpose**: Update plugin configuration
  - **Returns**: Update status
  - **Data**: Plugin configuration update confirmation

### Traffic Reset Management
- **GET** `/api/v2/{admin_path}/traffic-reset/logs`
  - **Purpose**: Get traffic reset logs
  - **Returns**: Reset log entries
  - **Data**: Traffic reset history and details

- **GET** `/api/v2/{admin_path}/traffic-reset/stats`
  - **Purpose**: Get traffic reset statistics
  - **Returns**: Reset statistics
  - **Data**: Traffic reset metrics and trends

- **GET** `/api/v2/{admin_path}/traffic-reset/user/{userId}/history`
  - **Purpose**: Get user traffic reset history
  - **Returns**: User-specific reset history
  - **Data**: Individual user reset records

- **POST** `/api/v2/{admin_path}/traffic-reset/reset-user`
  - **Purpose**: Reset user traffic
  - **Returns**: Reset status
  - **Data**: User traffic reset confirmation

---

## 📝 Notes

1. **Admin Path**: The `{admin_path}` in V2 admin routes is dynamically generated based on the `secure_path` or `frontend_admin_path` configuration setting.

2. **Authentication Middleware**: 
   - `user`: Requires valid user authentication
   - `admin`: Requires admin privileges
   - `staff`: Requires staff privileges
   - `client`: Requires client token authentication
   - `server`: Requires server authentication

3. **Response Format**: Most endpoints return data in a standardized format:
   ```json
   {
     "data": [...], // Actual response data
     "message": "Success message",
     "status": true/false
   }
   ```

4. **Error Handling**: Failed requests return appropriate HTTP status codes with error messages.

5. **Pagination**: Many list endpoints support pagination with `current` and `pageSize` parameters.

6. **Filtering**: Admin endpoints often support filtering and sorting parameters.

This documentation provides a comprehensive overview of all available API endpoints in the Xboard system. Each endpoint serves specific functionality within the VPN service management platform.