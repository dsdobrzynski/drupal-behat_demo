<?php

use Behat\Behat\Context\ClosuredContextInterface,
Behat\Behat\Context\TranslatedContextInterface,
Behat\Behat\Context\BehatContext,
Behat\Behat\Exception\PendingException;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\OutlineExampleEvent;
use Behat\Behat\Context\Step\Given;
use Behat\Behat\Context\Step\Then;
use Behat\Behat\Context\Step\When;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Drupal\Component\Utility\Random;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\EventDispatcher\EventDispatcher;


/**
 * Features context.
 */
class DrupalCommerceContext extends BehatContext implements DrupalSubContextInterface
{

    private $products = array();
    private $orders = array();
    private $coupons = array();
    private $sku;
    private $product;
    private $assertDelegateClass = '\PHPUnit_Framework_Assert';

    public function __construct()
    {
        $random = new Random();
        $this->sku = $random->string(12);
    }

    public static function getAlias()
    {
        return 'commerce';
    }

    public function afterScenario($event)
    {
        if ($event instanceof ScenarioEvent || $event instanceof OutlineExampleEvent) {
            commerce_order_delete_multiple(array_keys($this->orders));
            commerce_product_delete_multiple(array_keys($this->products));
        }

        commerce_coupon_delete_multiple(array_keys($this->coupons));
    }

    public function beforeScenario($event) {
        $this->fullSession();
    }

    /**
     * @Given /^I have a "([^"]*)" product in my cart$/
     */
    public function iHaveAProductInMyCart($productType)
    {
        $cart = $this->getCart();
        $match = FALSE;
        $this->addProductsToCart(array($this->createProduct($productType)));
        $this->assertCartNotEmpty();
    }

    /**
     * @Given /^I have (product|a product with sku) "([^"]*)" in my cart$/
     */
    public function iHaveAProductWithSkuInMyCart($ignore, $sku)
    {
        $cart = $this->getCart();
        $match = FALSE;
        $this->addProductsToCart(array(commerce_product_load_by_sku($sku)));
        $this->assertCartNotEmpty();
    }

    public function addProductsToCart(array $products = array())
    {
        $cart = $this->getCart();
        $user = $this->getMainContext()->user;
        $uid = $user ? $user->uid : 0;
        foreach ($products as $product) {
            $line_item = commerce_product_line_item_new($product, 1, $cart->order_id->raw());
            commerce_cart_product_add($uid, $line_item);
        }
    }

    /**
     * @Then /^I should have a product with sku "([^"]*)" in my cart$/
     */
    public function assertProductSkuInMyCart($productSku)
    {
        $product = commerce_product_load_by_sku($productSku);
        $order = $this->getCart();

        $product_is_in_cart = FALSE;
        foreach ($order->commerce_line_items as $delta => $line_item_wrapper) {
            if (
                $line_item_wrapper->commerce_product->product_id->value()
                == $product->product_id
            ) {
                $product_is_in_cart = TRUE;
            }
        }
        $this->assertTrue(
            $product_is_in_cart,
            t(
                'Product !product_title is not present in the cart',
                array('!product_title' => $product->title)
            )
        );
    }

    /**
     * @Then /^my cart should be empty$/
     */
    public function assertCartEmpty()
    {
        $this->assertTrue(
            commerce_line_items_quantity(
                $this->getCart()->commerce_line_items,
                commerce_product_line_item_types()
            ) === 0,
            'Cart is Empty'
        );
    }

    /**
     * @Then /^My cart should not be empty$/
     */
    public function assertCartNotEmpty()
    {
        $this->assertTrue(
            commerce_line_items_quantity($this->getCart()->commerce_line_items, commerce_product_line_item_types()) > 0,
            'Cart is Empty'
        );
    }

    /**
     * @Then /^I should be on the commerce checkout page$/
     */
    public function assertOnCheckoutPage()
    {
        return new Then('the url should match "checkout/\d+"');
    }

    /**
     * @Given /^I am on the commerce checkout page$/
     */
    public function iAmOnTheCheckoutPage()
    {
        $order = $this->getCart()->value();
        $order = commerce_order_status_update($order, 'cart', TRUE);
        commerce_order_save($order);
        return new Given("I am at \"checkout/{$order->order_id}\"");
    }

    /**
     * @Then /^I should be on the IP Info checkout page$/
     */
    public function assertOnIpInfoPage()
    {
        return new Then('the url should match "checkout/\d+/ip_info"');
    }

    /**
     * @Then /^I should be on the order review page$/
     */
    public function assertOnOrderReviewPage()
    {
        return new Then("the url should match \"checkout/\d+/review\"");
    }


    /**
     * @Given /^I am on the order review page$/
     */
    public function iAmOnTheOrderReviewPage()
    {
        $order = $this->getCart()->value();
        return new Given("I am at \"checkout/{$order->order_id}/review\"");
    }

    /**
     * @Given /^anonymous order with email address "([^"]*)" and user id "([^"]*)"$/
     */
    public function orderWithEmailAddressAndUserId($email, $user_id)
    {
        $user = (object) array(
            'mail' => $user_id,
            'name' => $user_id
        );
        $user_wrapper = entity_metadata_wrapper('user', $user);
        $user_wrapper->field_frontier_user_id          = $user_id;
        $user_wrapper->field_contact_email             = $email;
        $user_wrapper->field_phone_number              = '7732468024';
        $user_wrapper->field_security_question         = 'social';
        $user_wrapper->field_security_question_answer  = '2468';

        $billing_wrapper = entity_metadata_wrapper(
            'commerce_customer_profile',
            commerce_customer_profile_new('billing'),
            array('bundle' => 'billing')
        );
        $address = $billing_wrapper->commerce_customer_address;
        $address->name_line           = NULL;
        $address->country             = 'US';
        $address->thoroughfare        = '1060 W Addison St';
        $address->administrative_area = 'IL';
        $address->postal_code         = '60613-4566';
        $address->locality            = 'Chicago';
        $name = $billing_wrapper->field_billing_name;
        $name->given  = 'bob';
        $name->family = 'jones';
        $billing_wrapper->save();

        $order_wrapper = $this->getCart();
        $order_wrapper->commerce_customer_billing = $billing_wrapper->getIdentifier();
        $order_wrapper->save();
        $order = $order_wrapper->value();
        $order->data['new_account'] = $user_wrapper->value();
        $order->mail = $email;
        $order->uid = 0;
        commerce_order_save($order);
    }

    /**
     * @Given /^"(?P<type>[^"]*)" products:$/
     */
    public function createProducts($type, TableNode $products)
    {
        $new_products = array();
        foreach ($products->getHash() as $productInfo) {
            $new_products[] = $this->createProduct($type, $productInfo);
        }
        return $new_products;
    }

    /**
     * @Given /^(?:a|an) "(?P<type>[^"]*)" product$/
     */
    public function createProduct($type, array $data = array())
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->sku = substr(md5($this->sku), 0, 12);
        }
        if (!empty($data['field_product_affiliate'])) {
            $term = taxonomy_get_term_by_name($data['field_product_affiliate']);
            $data['field_product_affiliate'] = (array_shift($term)->tid);
        }
        if (!empty($data['field_products_in_bundle'])) {
            $skus = array_map('trim', explode(',', $data['field_products_in_bundle']));
            $data['field_products_in_bundle'] = array();
            foreach ($skus as $sku) {
                if (!($product = commerce_product_load_by_sku($sku))) {
                    continue;
                }

                $data['field_products_in_bundle'][] = $product;
            }
        }
        if (!empty($data['field_addon_cart_requirement'])) {
            $skus = array_map(
                'trim',
                explode(',', $data['field_addon_cart_requirement'])
            );
            $data['field_addon_cart_requirement'] = array();
            foreach ($skus as $sku) {
                if (!($product = commerce_product_load_by_sku($sku))) {
                    continue;
                }

                $data['field_addon_cart_requirement'][] = $product;
            }
        }
        if (!$this->product = commerce_product_load_by_sku($data['sku'])) {
            $this->product = commerce_product_new($type);
            $product_wrapper = entity_metadata_wrapper('commerce_product', $this->product);
            foreach ($data as $attribute => $value) {
                $product_wrapper->{$attribute} = $value;
            }
            $product_wrapper->uid = 1;
            $product_wrapper->commerce_price->amount = 100;
            $product_wrapper->commerce_price->currency_code = 'USD';
            $map = [
                'product' => 'product_monthly_or_yearly',
                'bundle'  => 'bundle_monthly_or_yearly',
                'add_on'  => 'addon_price_type'
            ];
            $price_type_field = "field_{$map[$product_wrapper->type->value()]}";
            if (!$product_wrapper->$price_type_field->value()) {
                $product_wrapper->$price_type_field = 'month';
            }
            commerce_product_save($this->product);
            $this->skus[] = $data['sku'];
        }
        $this->products[$this->product->product_id] = $this->product;
        return $this->product;
    }

    /**
     * @Given /^"(?P<type>[^"]*)" coupons:$/
     */
    public function createCoupons($type, TableNode $coupons)
    {
        $new_coupons = array();
        foreach ($coupons->getHash() as $couponInfo) {
            $commerce_coupon = $this->createCoupon($type, $couponInfo);
            $this->coupons[$commerce_coupon->coupon_id] = $commerce_coupon;
            $new_coupons[] = $new_coupons;
        }
        return $new_coupons;
    }

    /**
     * @Given /^(?:a|an) "(?P<type>[^"]*)" coupon$/
     */
    public function createCoupon($type, array $data = array())
    {
        $commerce_coupon = entity_metadata_wrapper('commerce_coupon', commerce_coupon_create($type));
        $property_info = $commerce_coupon->getPropertyInfo();
        foreach ($data as $attribute => $value) {
            if ($property_info[$attribute]['type'] == 'commerce_price') {
                $commerce_coupon->$attribute->amount = $value;
                $commerce_coupon->$attribute->currency_code = commerce_default_currency();
            }
            // Handle Expiration date in a special manner where format is
            // considered as 'date1' - 'date2'. Entity metadatawrapper also can't
            // set dates.
            elseif ($attribute == 'field_coupon_expiration') {
                list($value1, $value2) = array_map('trim', explode('-', $value));
                $date1 = new DateObject($value1);
                $date2 = new DateObject($value2);
                $commerce_coupon->value()->field_coupon_expiration[LANGUAGE_NONE][0]['value'] = $date1->format('Y-m-d h:i:s', TRUE);
                $commerce_coupon->value()->field_coupon_expiration[LANGUAGE_NONE][0]['value2'] = $date2->format('Y-m-d h:i:s', TRUE);
                $commerce_coupon->value()->field_coupon_expiration[LANGUAGE_NONE][0]['timezone'] = 'UTC';
                $commerce_coupon->value()->field_coupon_expiration[LANGUAGE_NONE][0]['timezone_db'] = 'UTC';
            }
            else {
                $commerce_coupon->$attribute = $value;
            }
        }

        $commerce_coupon->save();
        $this->coupon = $commerce_coupon->value();
        $this->coupons[$commerce_coupon->coupon_id->value()] = $commerce_coupon;
        return $this->coupon;
    }

    protected function getLastNodeID($type, $title)
    {
        $query = new EntityFieldQuery();

        $entities = $query->entityCondition('entity_type', 'node')
            ->propertyCondition('type', $type)
            ->propertyCondition('title', $title)
            ->propertyCondition('status', 1)
            ->execute();

        if (!empty($entities['node'])) {
            $node = end($entities['node']);
        }
        return $node->nid;
    }

    /**
     * @Given /^(?:a|an) "(?P<type>[^"]*)" node with the title "(?P<title>[^"]*)" that references "(?P<sku>[^"]*)"$/
     */
    public function createNodeWithReference($type, $title, $sku)
    {
        $product = commerce_product_load_by_sku($sku);
        $nid = $this->getLastNodeID($type, $title);
        $node = node_load($nid);
        $wrapper = entity_metadata_wrapper('node', $node);
        $wrapper->field_product_reference[0]->set($product);
        $wrapper->save();
    }

    public function fullSession()
    {
        foreach (array('selenium2', 'goutte') as $session) {
            $session = $this->getMainContext()->getMink()->getSession($session);
            $session->setCookie(
                $this->getMainContext()->getDrupalSession()->name,
                $this->getMainContext()->getDrupalSession()->id
            );
        }
    }

    protected function getCart()
    {
        $user = $this->getMainContext()->user;
        $uid  = $user ? $user->uid : 0;
        $this->fullSession();
        drupal_static_reset('commerce_cart_order_id');
        drupal_session_started(FALSE);
        drupal_session_start();
        $cart = commerce_cart_order_load($uid) ?: commerce_cart_order_new();
        drupal_session_commit();
        return $this->orders[$cart->order_id] = entity_metadata_wrapper('commerce_order', $cart);
    }

    /**
     * @Given /^I complete checkout$/
     */
    public function iCompleteCheckout()
    {
        $cart = $this->getCart();
        commerce_checkout_complete($cart->value());
    }

    public function __call($name, array $args = array())
    {
        if (strpos($name, 'assert') !== FALSE) {
            return call_user_func_array("{$this->assertDelegateClass}::$name", $args);
        }
    }

    /**
     * @Given /^I am in checkout with "(?P<type>[^"]*)" products:$/
     */
    public function iAmInCheckoutWithProducts($type, TableNode $table)
    {
        $cart = $this->getCart();
        $this->addProductsToCart($this->createProducts($type, $table));
        $order_state = commerce_order_state_load('checkout');
        $cart = commerce_order_status_update($cart->value(), $order_state['default_status']);
        return new Given('I am at "/checkout/'.$cart->order_id.'"');
    }

    /**
     * @When /^I select the ajax radio button "(?P<label>[^"]*)" with the id "(?P<id>[^"]*)"$/
     * @When /^I select the ajax radio button "(?P<label>[^"]*)"$/
     */
    public function ajaxRadio($label, $id = FALSE)
    {
        $this->getMainContext()->assertSelectRadioById($label, $id);
        $this->getMainContext()->getSession()->wait(2000);
    }

    /**
     * @Given /^I am in checkout with small business products$/
     */
    public function iAmInCheckoutWithSmallBusinessProducts()
    {
        $cart = $this->getCart();

        $term_search = taxonomy_get_term_by_name('Small Business');
        $term        = current($term_search);

        $this->addProductsToCart(array($this->createProduct('product',
          array('field_commerce_product_customer' => $term->tid))));
        $this->assertCartNotEmpty();
        return new Given('I am on the commerce checkout page');
    }

    /**
     * @Given /^I am in checkout without small business products$/
     */
    public function iAmInCheckoutWithoutSmallBusinessProducts()
    {
        $cart = $this->getCart();

        $this->addProductsToCart(array($this->createProduct('product')));
        $this->assertCartNotEmpty();
        return new Given('I am on the commerce checkout page');
    }

    /**
     * @Given /^I am in checkout with CI products$/
     */
    public function iAmInCheckoutWithCIProducts()
    {
        return new Given('I am in checkout with "product" products:', new TableNode(
            <<<TABLE
                | sku     |
                | FS1015  |
TABLE
        ));
    }


    /**
     * @When /^I fill in address and payment information$/
     */
    public function iFillInAddressAndPaymentInformation()
    {
        $fillIn = [
            ['Bob', 'First name', 'billing'],
            ['Jones', 'Last name', 'billing'],
            ['108 5th Ave NW', 'Address 1', 'billing'],
            ['Altoona', 'City', 'billing'],
            ['50009', 'ZIP Code', 'billing'],
            ['7732224444', 'Phone Number', 'new_account'],
            ['bob@thejones.net', 'User ID', 'new_account'],
            ['bob@theotherjones.net', 'Contact Email', 'new_account'],
            ['social', 'Security Question', 'new_account'],
            ['1234', 'Security Question Answer', 'new_account'],
        ];
        $pattern = 'I fill in "%s" for "%s" in the "%s" region';
        foreach ($fillIn as $fill) {
            $step = sprintf($pattern, $fill[0], $fill[1], $fill[2]);
            $steps[] = new When($step);
        }
        $steps[] = new When('I select "IA" from "State"');
        $steps[] = new When('I select the ajax radio button "Manual (Cash or Check)"');
        return $steps;
    }

    /**
     * @Then /^I must fill in "([^"]*)" in the "([^"]*)" region$/
     */
    public function iMustFillInInTheRegion($locator, $region)
    {
        $page = $this->getMainContext()->getSession()->getPage();
        $regionObject = $page->find('region', $region);

        $this->assertNotNull($regionObject, t('Unable to find the region @region.',
            array('@region' => $region)));

        if ($regionObject !== NULL) {
            // Make sure that the form element exists on the page.
            $field = $regionObject->findField($locator);
            $this->assertNotNull($field);

            // Make sure that the fields are marked as 'required' as well by
            // checking that the class has 'required' in it.
            if ($field !== NULL && ($class = $field->getAttribute('class'))) {
              $this->assertNotFalse(strpos($class, 'required'),
                t('The field @field is marked as required.', array('@field' => $locator)));
            }
        }
    }

    /**
     * @Given /^I am in checkout with physical products$/
     */
    public function iAmInCheckoutWithPhysicalProducts()
    {
        $cart = $this->getCart();
        $this->addProductsToCart(array($this->createProduct(
          'product',
          array('field_commerce_product_shippable' => TRUE)
        )));
        $this->assertCartNotEmpty();
        return new Given('I am on the commerce checkout page');
    }

    /**
     * @Given /^I am in checkout without physical products$/
     */
    public function iAmInCheckoutWithoutPhysicalProducts()
    {
        $cart = $this->getCart();
        // Add a 'shippable' product in the cart.
        $this->addProductsToCart(array($this->createProduct('product')));
        $this->assertCartNotEmpty();
        return new Given('I am on the commerce checkout page');
    }

    /**
     * @Then /^I should not see the "([^"]*)" region$/
     */
    public function iShouldNotSeeTheRegion($region)
    {
        $page = $this->getMainContext()->getSession()->getPage();
        $regionObject = $page->find('region', $region);
        $this->assertNull($regionObject, t('@region region is not found.',
            array('@region' => $region)));
    }

    /**
     * @Given /^I am in checkout$/
     */
    public function iAmInCheckout()
    {
        throw new PendingException();
    }

    /**
     * @Then /^I should have to fill out information for each CI product$/
     */
    public function iShouldHaveToFillOutInformationForEachCiProduct()
    {
        $cart = $this->getCart();
        $info_count = frontier_ip_order_ip_info_count($cart);
        $message = sprintf("You must submit customer information for %d customers", $info_count);
        return array(
            new When('I press the "Review Order" button'),
            new Then("I should see the message \"$message\"")
        );
    }

    /**
     * @When /^I fill out information for each CI product$/
     */
    public function iFillOutInformationForEachCiProduct()
    {
        $fillIn = [
            ['Bob', 'First name'],
            ['Jones', 'Last name'],
            ['1060 W Addison St', 'Address 1'],
            ['Chicago', 'City'],
            ['60613', 'ZIP Code'],
            ['7732224444', 'Phone Number'],
            ['bob@thejones.net', 'Frontier User Id'],
            ['bob@theotherjones.net', 'Contact Email'],
            ['1234', 'SSN Last 4'],
            ['foo', 'Challenge Answer'],
            ['foobar', 'Password'],
        ];
        $steps[] = new When('I press the "ADD MEMBER" ajax button');
        $pattern = 'I fill in "%s" for "%s"';
        foreach ($fillIn as $fill) {
            $step = sprintf($pattern, $fill[0], $fill[1]);
            $steps[] = new When($step);
        }
        $steps[] = new When('I select "first_car" from "Challenge Question"');
        $steps[] = new When('I select "IL" from "State"');
        $steps[] = new When('I press the "Submit Member Details" ajax button');
        $steps[] = new When('I press the "Review Order" button');
        /* throw new PendingException('There is something dumb with these AJAX forms'); */
        return $steps;
    }

    /**
     * @When /^(?:|I )press the "(?P<button>[^"]*)" ajax button$/
     */
    public function ajaxPressButton($button)
    {
        $this->getMainContext()->pressButton($button);
        $this->getMainContext()->getSession()->wait(2000, '(0 === jQuery.active)');
    }

    /**
     * @Given /^product "([^"]*)" with addon "([^"]*)"$/
     */
    public function productWithAddon($productSku, $addonSku) {
        $product  = $this->createProduct('product', ['sku' => $productSku]);
        $addon    = $this->createProduct('add_on', ['sku' => $addonSku]);
        $wrapper  = entity_metadata_wrapper('commerce_product', $addon);
        $wrapper->field_addon_cart_requirement = [$product];
        $wrapper->save();
    }

    /**
     * @Then /^I should have product "([^"]*)" in my cart$/
     */
    public function iShouldHaveProductInMyCart($productSku) {
        $cart = $this->getCart();
        $found = false;
        foreach($cart->commerce_line_items as $li) {
            if (
                in_array($li->type->value(), commerce_product_line_item_types())
                && $li->commerce_product->sku->value() == $productSku
            ) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @When /^I remove product "([^"]*)" from my cart$/
     */
    public function iRemoveProductFromMyCart($productSku) {
        $cart = $this->getCart();
        foreach($cart->commerce_line_items as $li) {
            if (
                in_array($li->type->value(), commerce_product_line_item_types())
                && $li->commerce_product->sku->value() == $productSku
            ) {
                break;
            }
        }
        commerce_cart_order_product_line_item_delete(
            $cart->value(),
            $li->getIdentifier(),
            TRUE
        );
    }
}
