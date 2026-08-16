<?php

$policy = require __DIR__.'/commercial-policies.php';

return [
    'terms-and-conditions' => [
        'title' => 'Terms and Conditions',
        'description' => 'The terms that apply when you browse Kattie.uk or place an order with us.',
        'sections' => [
            ['Using Kattie.uk', ['By using this website, you agree to use it lawfully and not interfere with its security or operation. Product information, availability and prices may be updated before an order is accepted.']],
            ['Orders and payment', ['Your order is an offer to buy. A contract is formed when we accept the order and send confirmation. Prices are shown in pounds sterling. Delivery charges and the final total are displayed before payment.']],
            ['Personalised products', ['Please check names, options, photographs and artwork carefully before approving a design. Each product is made to order using your approved personalised artwork. Personalised goods cannot normally be returned or exchanged simply because you change your mind.']],
            ['Changes and cancellations', [$policy['cancellation']]],
            ['Photographs and uploaded content', ['You confirm that you own, or have permission to use, every photograph, name and other item you upload. We may reject content that is unlawful, harmful, infringing or unsuitable for production. Your content is used to create and fulfil your personalised order.']],
            ['Product appearance', ['Screen colours and previews are a helpful guide, but small differences can occur because of displays, materials and printing processes. Product photography is not a personalised mockup unless we say otherwise.']],
            ['Delivery, returns and liability', ['Production normally takes '.$policy['production_time'].'. Delivery estimates begin after production and are not guarantees. If an item is damaged, defective, incorrect or not as described, contact us so we can review the issue. This does not affect your statutory rights.']],
            ['Contact', ['Questions about these terms can be sent to support@kattie.uk.']],
        ],
    ],
    'faq' => [
        'title' => 'FAQ',
        'description' => 'Quick answers about personalisation, orders, delivery and returns.',
        'sections' => [
            ['How does personalisation work?', ['Choose a product and its options, enter the requested name and upload a suitable photograph. You can review the generated artwork and adjust supported designs before adding the item to your basket.']],
            ['What kind of photograph should I upload?', ['Use a clear, well-lit image with the subject fully visible. JPEG, PNG and WebP files up to 10 MB are supported. Only upload photographs you have permission to use.']],
            ['Can I change or cancel my order?', [$policy['cancellation']]],
            ['How long will my order take?', ['Production normally takes '.$policy['production_time'].'. UK delivery then takes approximately 1–4 working days after dispatch, depending on the selected method. Times are estimates and may occasionally vary.']],
            ['Can I return a personalised item?', ['Because personalised items are made to order, we cannot normally accept a return or exchange if you simply change your mind. If an item is damaged, defective, incorrect or not as described, please contact us. This does not affect your statutory rights.']],
            ['Where is my order?', ['Tracking details will be provided when they are available. If an expected parcel has not arrived, contact support@kattie.uk with your order number.']],
            ['What if my item is wrong or damaged?', ['Contact us as soon as possible with your order number, a description of the problem and clear photographs. We will review the issue and explain whether a replacement or refund is appropriate.']],
            ['How do refunds work?', ['Where a refund is appropriate and approved, it is returned to the original payment method. The time taken to appear in your account depends on the payment provider.']],
        ],
    ],
    'delivery-shipping' => [
        'title' => 'Delivery & Shipping',
        'description' => 'Production and estimated delivery times for personalised Kattie orders in the United Kingdom.',
        'sections' => [
            ['Production and delivery', ['Each item is made to order after your personalised artwork and payment have been approved. The delivery estimates shown below are the total expected times and already include production.']],
            ['Tracking', ['Where tracking is available, we will provide it after dispatch. Tracking can take a little time to show its first carrier update.']],
            ['Delays and address changes', ['Busy periods, weather and carrier disruption may occasionally extend delivery estimates. Contact support@kattie.uk promptly if an address needs correcting. We cannot normally redirect a parcel after dispatch.']],
            ['Sending directly as a gift', ['You may use the recipient’s UK address at checkout. Please check it carefully, including the postcode, before confirming the order.']],
        ],
        'delivery_table' => ['production_heading' => $policy['production_heading'], 'production_message' => $policy['production_message'], 'methods' => $policy['delivery_methods'], 'disclaimer' => $policy['delivery_disclaimer']],
    ],
    'returns-policy' => [
        'title' => 'Returns Policy',
        'description' => 'Our approach to returns, replacements and refunds for personalised gifts.',
        'sections' => [
            ['Personalised Products', ['Each Kattie product is made especially for you using your approved personalised artwork. Because personalised items are made to order, we cannot normally accept returns or exchanges if you simply change your mind.']],
            ['Damaged or Defective Items', ['If your item arrives damaged or defective, contact support@kattie.uk as soon as possible with your order number and a description of the issue. We may ask for clear photographs so we can assess what happened and put it right.']],
            ['Incorrect Items', ['Please contact us if you receive an incorrect item or something that does not match its description or your approved order. Keep the item and packaging while we review the issue, and do not return anything until we provide instructions.']],
            ['Refunds and Replacements', ['Depending on the circumstances, we may offer an appropriate replacement or refund. We do not promise an automatic outcome before reviewing the issue. Approved refunds are returned to the original payment method and may take additional working days to appear.']],
            ['Cancellations', [$policy['cancellation']]],
            ['How to Contact Us', ['Email support@kattie.uk with your order number, a short explanation and any requested photographs. We will review the information and explain the next steps.']],
            ['Statutory Rights', ['This policy does not limit your rights where an item is faulty, damaged, incorrect or not as described. This does not affect your statutory rights.']],
        ],
    ],
    'privacy-policy' => [
        'title' => 'Privacy Policy',
        'description' => 'How Kattie.uk uses and protects personal information, including uploaded photographs.',
        'sections' => [
            ['Information we collect', ['We collect information you provide when browsing, personalising or ordering, such as names, contact and delivery details, order information, uploaded photographs, artwork choices and messages sent to support. Technical information such as IP address, browser details and necessary cookie identifiers may also be recorded.']],
            ['How we use information', ['We use personal information to operate the website, create personalised artwork, fulfil orders, provide customer support, prevent misuse, maintain security and meet legal obligations. Where required, processing is based on performing our contract, legitimate interests, consent or legal duties.']],
            ['Photographs and AI artwork', ['Uploaded photographs and related personalisation are processed to provide the requested artwork and product. Please obtain permission before uploading an image of another person, especially a child. We do not ask you to upload unnecessary sensitive information.']],
            ['Who receives information', ['Information may be shared only as needed with service providers supporting hosting, artwork generation, payments, production, delivery, analytics or customer service, and with authorities where required by law. Providers must use it for the agreed service and protect it appropriately.']],
            ['Retention and security', ['We keep information only for as long as needed for the purpose collected, legal requirements, dispute handling and fraud prevention. We use reasonable technical and organisational safeguards, although no internet service can guarantee absolute security.']],
            ['Cookies', ['Necessary cookies support features such as sessions and baskets. Other analytics or preference technologies will be described and controlled through the cookie choices available on the site when introduced.']],
            ['Your rights', ['Depending on applicable UK data protection law, you may ask to access, correct, erase or restrict personal information, object to certain uses, or request portability. You may also withdraw consent where consent is the legal basis.']],
            ['Contact', ['Privacy questions and rights requests can be sent to support@kattie.uk. We may need to verify your identity before completing a request.']],
        ],
    ],
    'manage-cookies' => [
        'title' => 'Manage Cookies',
        'description' => 'Information about the cookies Kattie.uk uses and the choices available to you.',
        'sections' => [
            ['What cookies are', ['Cookies are small text files stored by your browser. They help websites remember information between page requests and provide secure, consistent functionality.']],
            ['Necessary cookies', ['Kattie.uk uses necessary cookies for essential features such as secure sessions, artwork personalisation and your basket. These cookies cannot be switched off through the website because the service may not work correctly without them.']],
            ['Optional cookies', ['If analytics, advertising or preference cookies are introduced, they will be described clearly and will not be enabled without the choice or consent required by applicable law.']],
            ['Your browser settings', ['You can view, block or delete cookies using your browser settings. Blocking necessary cookies may prevent personalisation, basket and checkout features from working correctly.']],
            ['Questions', ['For questions about cookies or personal information, email support@kattie.uk or read our Privacy Policy.']],
        ],
    ],
    'payment-methods' => [
        'title' => 'Payment Methods',
        'description' => 'Information about checkout, currency, payment security and failed payments.',
        'sections' => [
            ['Available payment methods', ['The payment options currently available to you are displayed at checkout. We will not claim to accept a card, wallet or instalment provider unless that option is shown before you place the order.']],
            ['Currency and total', ['Kattie.uk displays prices in pounds sterling. The complete payable total, including any confirmed delivery charge, is shown before payment is authorised.']],
            ['Payment security', ['Payments are handled through the secure payment service presented at checkout. Kattie.uk does not need to store your complete card number or card security code when payment is processed by that provider.']],
            ['When payment is taken', ['Your order is submitted when the checkout confirms payment or another approved payment state. Do not close or refresh the payment screen while a transaction is being confirmed.']],
            ['If payment fails', ['Check that the payment details, billing information and available funds are correct, then try again. Your bank may decline a payment without sharing the reason with us. If the problem continues, contact your provider or email support@kattie.uk with the error message, but never send full card details.']],
            ['Refunds', ['Where a refund is appropriate and approved, it is sent to the original payment method. Processing time after issue is controlled by the bank or payment provider.']],
        ],
    ],
];
