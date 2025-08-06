# WooCommerce BR-DGE Payment Gateway

A WordPress plugin that integrates BR-DGE payment orchestration platform with WooCommerce, enabling merchants to accept payments through multiple payment service providers via a single integration.

## Features

- **Secure Payments**: PCI-compliant hosted fields for secure card data collection
- **Multiple Payment Methods**: Support for credit/debit cards and digital wallets
- **3D Secure Support**: Automatic handling of 3D Secure authentication
- **Smart Routing**: Leverage BR-DGE's intelligent payment routing
- **Refund Support**: Process full and partial refunds directly from WooCommerce
- **Webhook Integration**: Real-time payment status updates
- **Test Mode**: Comprehensive testing environment with sandbox API
- **Responsive Design**: Mobile-friendly payment forms

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (required for live payments)
- BR-DGE merchant account

## Installation

1. **Download the Plugin**
   - Download the plugin files
   - Create a folder named `wc-brdge-gateway` in your WordPress plugins directory

2. **Upload Files**
   ```
   wp-content/plugins/wc-brdge-gateway/
   ├── wc-brdge-gateway.php (main plugin file)
   ├── class-wc-brdge-gateway.php (gateway class)
   ├── assets/
   │   ├── js/
   │   │   └── checkout.js
   │   └── css/
   │       └── checkout.css
   └── README.md
   ```

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "WooCommerce BR-DGE Payment Gateway"
   - Click "Activate"

## Configuration

### 1. Get BR-DGE API Keys

1. Log in to your [BR-DGE Portal](https://portal.br-dge.io)
2. Navigate to API Keys section
3. Generate both Server and Client API keys for:
   - **Test Environment** (for development/testing)
   - **Live Environment** (for production)

### 2. Configure the Gateway

1. Go to **WooCommerce → Settings → Payments**
2. Find "BR-DGE Payment Gateway" and click "Manage"
3. Configure the following settings:

#### Basic Settings
- **Enable/Disable**: Check to enable the payment method
- **Title**: Display name for customers (e.g., "Credit Card")
- **Description**: Customer-facing description
- **Test Mode**: Enable for testing with sandbox API

#### API Configuration
- **Test Server API Key**: Your BR-DGE test server key
- **Test Client API Key**: Your BR-DGE test client key
- **Live Server API Key**: Your BR-DGE live server key
- **Live Client API Key**: Your BR-DGE live client key
- **Webhook Secret**: Optional secret for webhook validation

### 3. Set Up Webhooks

1. In your BR-DGE Portal, configure webhook URLs:
   - **Webhook URL**: `https://yoursite.com/wp-json/wc-brdge/v1/webhook`
   - **Events**: Select payment status change events

2. Configure the webhook secret in your plugin settings for enhanced security

## Testing

### Test Mode Setup

1. Enable "Test Mode" in the gateway settings
2. Use your test API keys
3. Use BR-DGE test card numbers:

```
Visa: 4111111111111111
Mastercard: 5555555555554444
American Express: 378282246310005
```

### Test Payment Flow

1. Add products to cart
2. Proceed to checkout
3. Select BR-DGE as payment method
4. Enter test card details
5. Complete the purchase
6. Verify payment status in WooCommerce and BR-DGE Portal

## Usage

### Customer Experience

1. **Checkout**: Customers select BR-DGE payment method
2. **Card Entry**: Secure hosted fields collect card information
3. **Processing**: Payment is processed through BR-DGE
4. **Confirmation**: Customer receives confirmation and order details

### Admin Features

- **Order Management**: View payment details in order admin
- **Refunds**: Process refunds directly from order page
- **Transaction Logs**: Debug payment issues with detailed logs
- **Status Sync**: Automatic order status updates via webhooks

## API Endpoints

The plugin creates the following endpoints:

- `POST /wp-json/wc-brdge/v1/webhook` - Webhook receiver for payment updates

## Security

### Best Practices

1. **API Keys**: Never expose server API keys in client-side code
2. **SSL Required**: Always use HTTPS for live payments
3. **Webhook Validation**: Use webhook secrets to verify authenticity
4. **PCI Compliance**: Hosted fields ensure card data never touches your server

### Data Handling

- Card data is handled entirely by BR-DGE's PCI-compliant systems
- Only payment tokens are stored in your WooCommerce database
- Customer billing information is encrypted in WordPress

## Troubleshooting

### Common Issues

**Payment Form Not Loading**
- Check API keys are correct and active
- Verify JavaScript console for errors
- Ensure WooCommerce is updated

**Payments Failing**
- Confirm test/live mode matches API keys
- Check BR-DGE Portal for declined transactions
- Verify webhook URL is accessible

**Webhook Issues**
- Test webhook URL responds with 200 status
- Check webhook secret configuration
- Review server error logs

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log`

## Support

### Resources

- [BR-DGE Documentation](https://docs.br-dge.io)
- [BR-DGE Portal](https://portal.br-dge.io)
- [WooCommerce Documentation](https://docs.woocommerce.com)

### Getting Help

1. **BR-DGE Support**: Contact for API and payment processing issues
2. **Plugin Issues**: Check WordPress/WooCommerce compatibility
3. **Integration Help**: Consult BR-DGE documentation for advanced features

## Changelog

### Version 1.0.0
- Initial release
- Basic payment processing
- Refund support
- Webhook integration
- Test mode support
- 3D Secure handling

## License

This plugin is proprietary software. Please refer to your BR-DGE merchant agreement for licensing terms.

## Contributing

For feature requests or bug reports, please contact your BR-DGE account manager or support team.