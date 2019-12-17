## Flow API Integration

### Installation

### Configuration

In your `.env` file:

```
FLOW_API_USERNAME="<username>"
FLOW_API_PASSWORD="<password>"
FLOW_PRODUCTS_URL="http://<url>:<port>/api/products"
FLOW_STOCK_URL="http://<url>:<port>/api/stockonhand"
FLOW_PRICING_URL="http://<url>:<port>/api/customerprice"
FLOW_ORDERS_URL="http://<url>:<port>/api/orders"
```

### Enable logging

```yaml

---
Name: flowlogging
Before:
  - '#logging'
  - '#sentrylogging'
---
SilverStripe\Core\Injector\Injector:
  Psr\Log\LoggerInterface:
    calls:
      FlowErrorHandler: [ pushHandler, [ '%$FlowErrorHandler' ] ]
  FlowErrorHandler:
    class: Isobar\Flow\Handler\FlowErrorHandler
    constructor:
      - admin@domain.com #to
      - There was an error on your test site #subject
      - from@domain.com #from
      - error
      - webadmin@domain.com #bcc
    properties:
      ContentType: text/html
      Formatter: %$SilverStripe\Logging\DetailedErrorFormatter

```
