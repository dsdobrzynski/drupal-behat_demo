<?php

use Behat\Behat\Context\ClosuredContextInterface,
Behat\Behat\Context\TranslatedContextInterface,
Behat\Behat\Context\BehatContext,
Behat\Behat\Context\Step\Given,
Behat\Behat\Exception\PendingException;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\BackgroundEvent;
use Behat\Behat\Event\OutlineExampleEvent;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\DrupalContext;
use Drupal\DrupalExtension\Event\EntityEvent;
use Drupal\Component\Utility\Random;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Features context.
 */
class FeatureContext extends DrupalContext
{
    private $assertDelegateClass = '\PHPUnit_Framework_Assert';
    private $drupalSession = FALSE;
    protected $menu_links = array();

    /**
     * Every scenario gets its own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
    }

    public function beforeScenario($event)
    {
        parent::beforeScenario($event);
        // @todo provide our own mail system to ameliorate ensuing ugliness.
        if (module_exists('devel')) {
            variable_set('mail_system', array('default-system' => 'DevelMailLog'));
        }
        else {
            throw new \Exception('You must ensure that the devel module is enabled');
        }
        if ($event instanceof ScenarioEvent) {
            $fs = new Filesystem();
            if ($mail_path = $event->getScenario()->getTitle()) {
              $fs->remove('/tmp/' . $mail_path);
              $fs->mkdir($mail_path);
            }
            variable_set('devel_debug_mail_directory', $mail_path);
            // NB: DevelMailLog is going to replace our separator with __.
            variable_set('devel_debug_mail_file_format', '%to::%subject');
            $this->mail = new \DevelMailLog();
        }
        if (!$this->drupalSession) {
            $_SERVER['SERVER_SOFTWARE'] = 'foo';
            $this->drupalSession = (object) array(
                'name' => session_name(),
                'id'   => session_id()
            );
            $_SESSION['foo'] = 'bar';
            drupal_session_commit();
        }
        session_name($this->drupalSession->name);
        session_id($this->drupalSession->id);
        $_COOKIE[session_name()] = session_id();
        drupal_session_start();
        foreach (array('selenium2', 'goutte') as $session) {
            $session = $this->getMink()->getSession($session);
            $session->visit($this->locatePath('/index.php?foo=bar'));
        }
        $scenario = $event instanceof ScenarioEvent
            ? $event->getScenario()
            : $event->getOutline();
        if (in_array('commerce', $scenario->getTags())) {
            $this->getSubcontext('commerce')->beforeScenario($event);
        }
    }

    public function assertAnonymousUser()
    {
        parent::assertAnonymousUser();
    }

    public function afterScenario($event)
    {
        // Allow clean up.
        parent::afterScenario($event);

        $this->drupalSession = FALSE;
    }

    /** @AfterScenario @menu */
    public function menuLinkCleanUp($event)
    {
        $menu_links = $this->menu_links;
        foreach ($menu_links as $menu_link) {
           menu_link_delete(NULL, $menu_link);
        }
    }

    public function getDrupalSession()
    {
        return $this->drupalSession;
    }

    public function logout()
    {
        global $user;
        $user = drupal_anonymous_user();
    }

    /**
     * Determine if the a user is already logged in.
     */
    public function loggedIn()
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/'));

        $page = $session->getPage();
        //Using the body class logged-in to determine if user is logged in
        return $page->find('css', '.logged-in');
    }

    /**
     * @Then /^there should be a user with email "([^"]*)"$/
     */
    public function thereShouldBeAUserWithEmail($email)
    {
        $efq = new \EntityFieldQuery();
        $users = $efq->entityCondition('entity_type', 'user')
            ->propertyCondition('mail', $email)
            ->count()
            ->execute();
        $this->assertNotEquals(0, $users, 'Failed to find user with email address: ' . $email);
    }

    /**
     * @Then /^save last response as "([^"]*)"$/
     */
    public function saveHtml($name)
    {
        $filename = DRUPAL_ROOT.DIRECTORY_SEPARATOR.$name.'.html';
        file_put_contents($filename, $this->getSession()->getPage()->getContent());
    }

    /**
     * @Then /^we should send an email to "([^"]*)"$/
     */
    public function weShouldSendAnEmailTo($email)
    {
        $this->assertNotEmpty(glob("{$this->mail->getOutputDirectory()}/$email*"));
    }

    /**
     * @Then /^we should send emails:$/
     */
    public function weShouldSendEmails(tableNode $emails)
    {
        foreach ($emails->getHash() as $email) {
            $fields = array_keys($email);
            $criteria = ['to', 'subject', 'body'];
            $find = [];
            foreach ($criteria as $criterion) {
                $find[$criterion] = isset($email[$criterion]) ? $email[$criterion] : '';
            }
            if (empty($find)) {
                throw new \Exception('Must include one of: ' . implode(', ', $criteria));
            }
            $path = str_replace(' ', '\\ ', $this->mail->getOutputDirectory()) . '/';
            $path .= $find['to'] ? "*{$find['to']}*" : '*';
            // @todo more ugliness from DevelMailLog
            $path .= '__';
            $path .= $find['subject'] ? '*'.str_replace(' ', '_', $find['subject']).'*' : '*';
            $this->assertNotEmpty($files = glob($path), "File $path not found");
            foreach ($files as $file) {
                if ($find['body']) {
                    $this->assertNotFalse(substr(file_get_contents($file), $find['body']));
                }
            }
        }
    }

    /**
     * @Then /^we should send new account activation emails:$/
     */
    public function weShouldSendNewAccountEmail(tableNode $emails)
    {
        foreach ($emails->getHash() as $email) {
            $fields = array_keys($email);
            $criteria = ['to', 'subject', 'body'];
            $find = [];
            $email['subject'] = _user_mail_text(
              'register_no_approval_required_subject',
              NULL,
              array(),
              FALSE
            );
            foreach ($criteria as $criterion) {
                $find[$criterion] = isset($email[$criterion]) ? $email[$criterion] : '';
            }
            if (empty($find)) {
                throw new \Exception('Must include one of: ' . implode(', ', $criteria));
            }
            $path = str_replace(' ', '\\ ', $this->mail->getOutputDirectory()) . '/';
            $path .= $find['to'] ? "*{$find['to']}*" : '*';
            // @todo more ugliness from DevelMailLog
            $this->assertNotEmpty($files = glob($path), "File $path not found");
            foreach ($files as $file) {
                if ($find['body']) {
                    $this->assertNotFalse(substr(file_get_contents($file), $find['body']));
                }
            }
        }
    }

    public function __call($name, array $args = array())
    {
        if (strpos($name, 'assert') !== FALSE) {
            return call_user_func_array("{$this->assertDelegateClass}::$name", $args);
        }
    }

    /**
     * @Given /^I am on (?:a|an) "(?P<type>[^"]*)" node with the title "(?P<title>[^"]*)"$/
     */
    public function assertOnNode($type, $title)
    {
        $nid = $this->getLastNodeID($type, $title);
        $path = ('node/' . $nid);
        return new Given("I am at \"$path\"");
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
     * @Given /^references to "(?P<skus>[^"]*)" on "(?P<type>[^"]*)" nodes:$/
     */
    public function createNodesWithReference($skus, $type, TableNode $nodesTable)
    {
        $skus = explode(",", $skus);
        $products = array();
        foreach ($skus as $sku) {
            $product = commerce_product_load_by_sku($sku);
            $product_id = $product->product_id;
            $products[] = $product_id;
        }

        foreach ($nodesTable->getHash() as $nodeHash)
        {
            $node = (object) $nodeHash;
            $node->type = $type;
            $node->field_product_reference = $products;

            $this->dispatcher->dispatch('beforeNodeCreate', new EntityEvent($this, $node));
            $saved = $this->getDriver()->createNode($node);
            $saved_wrapper = entity_metadata_wrapper('node', $saved);
            $saved_wrapper->field_product_reference->set($products);
            $saved_wrapper->save();
            node_save($saved);
            $this->dispatcher->dispatch('afterNodeCreate', new EntityEvent($this, $saved));
            $this->nodes[] = $saved;
        }
    }

    /**
     * @Transform /^(?P<link>[^"]*)"$/
     */
    public function waitForLink($link)
    {
        $this->getSession()->wait(1000, 'jQuery("#autocomplete").length === 0');
        return $link;
    }

    /**
     * @When /^we implement this$/
     *
     * Use to skip a scenario when steps are implemented but not functionality
     */
    public function weImplementThis()
    {
        throw new PendingException('This feature is not implemented');
    }

    /**
     * Creates users with roles. For some reason, the current createUsers
     * definition doesn't allow specifying roles. The header for roles *should*
     * be 'role_names'. If it's 'roles', userCreate() will complain.
     *
     * @Given /^users with roles:$/
     */
    public function createUsersWithRoles(TableNode $usersTable)
    {
        $this->createUsers($usersTable);

        // Once the users have been created we need to assign the roles to them.
        foreach ($usersTable->getHash() as $userHash) {
            if (!isset($userHash['role_names'])) {
                continue;
            }

            $roles = array_map('trim', explode(',', $userHash['role_names']));
            foreach ($roles as $role) {
                $this->getDriver()->userAddRole(user_load_by_name($userHash['name']), $role);
            }
        }
    }

    /**
     * @Given /^link to "(?P<type>[^"]*)" node "(?P<name>[^"]*)" in the "(?P<menu>[^"]*)"$/
     */
    public function createMenuLink($type, $name, $menu)
    {
        $menus = menu_get_menus();
        $machine_name = array_search($menu, $menus);
        $nid = $this->getLastNodeID($type, $name);
        $item = array(
            'link_path' => 'node/' . $nid,
            'link_title' => $name,
            'menu_name' => $machine_name,
            'weight' => 0,
            'plid' => 0,
            'module' => 'menu',
        );
        menu_link_save($item);
        $this->menu_links[] = $item['link_path'];
    }

    /**
     * @When /^I am on "([^"]*)" account of "([^"]*)"$/
     */
    public function iAmOnOperationAccount($operation, $name)
    {
        if ($account = user_load_by_name($name)) {
            if ($operation == 'view') {
              $this->getSession()->visit($this->locatePath('user/' . $account->uid));
            }
            elseif ($operation == 'edit') {
              $this->getSession()->visit($this->locatePath('user/' . $account->uid . '/edit'));
            }
        }
    }

    /**
     * Checks, that form element with specified label and type is visible on page.
     *
     * @Then /^(?:|I )should see an? "(?P<label>[^"]*)" (?P<type>[^"]*) form element$/
     */
    public function assertTypedFormElementOnPage($label, $type)
    {
        $container = $this->getSession()->getPage();
        $pattern = '/(^| )form-' . preg_quote($type). '($| )/';
        $label_nodes = $container->findAll('css', '.form-item');
        foreach ($label_nodes as $label_node) {
            // Note: getText() will return an empty string when using Selenium2D. This
            // is ok since it will cause a failed step.
            if ($label_node->getText() === $label
                && preg_match($pattern, $label_node->getParent()->getAttribute('class'))) {
                    return;
                }
        }
        throw new \Behat\Mink\Exception\ElementNotFoundException($this->getSession(), $type . ' form item', 'label', $label);
    }

    /**
     * @Given /^I should see only one message "([^"]*)"$/
     */
    public function iShouldSeeOnlyOneMessage($text) {
      $session = $this->getSession();
      $page = $session->getPage();

      $expandable = $page->findAll('xpath', '//span[contains(., "' . $text . '")]');
      $count = count($expandable);
      if ($count > 1) {
        throw new Exception("Should be only one message '$text' on the page, but find $count items.");
      }
    }

    /**
     * @Then /^fieldset with class "([^"]*)" should be opens$/
     */
    public function fieldsetWithClassShouldBeOpens($class) {
        $page = $this->getSession()->getPage();
        $items = $page->findAll('css', ".$class");
        foreach ($items as $item) {
            $result = $item->getParent()->find('css', '.ui-state-active');
        }
        if ((count($items) > 1) || empty($result)) {
            throw new \InvalidArgumentException(sprintf('Could not open fieldset with: "%s" class', $class));
        }
    }

    /**
     * @When /^I click "([^"]*)" with class "([^"]*)"$/
     */
    public function iClickWithClass($text, $class) {
        $session = $this->getSession();
        $element = $session->getPage()->find('css', "a.$class");
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $class));
        }
        $element->click();
    }

    /**
     * @When /^I click "([^"]*)" with ID "([^"]*)"$/
     */
    public function iClickWithId($text, $id) {
        $session = $this->getMainContext()->getSession();
        $element = $session->getPage()->find('css', "a#$id");
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $id));
        }
        $element->click();
    }

    /**
     * @Given /^I should see the element with xpath "([^"]*)"$/
     */
    public function iShouldSeeTheElementWithXpath($xpath) {
        $session = $this->getSession(); // get the mink session
        $element = $session->getPage()->find(
            'xpath',
            $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)
        ); // runs the actual query and returns the element

        // Errors must not pass silently.
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate XPath: "%s"', $xpath));
        }

        return $element;
    }

    /**
     * @Given /^I click the element with xpath "([^"]*)"$/
     */
    public function iClickTheElementWithXpath($xpath)
    {
        $element = $this->iShouldSeeTheElementWithXpath($xpath);

        $element->click();
    }

    /**
     * Fills in WYSIWYG editor with specified id.
     *
     * @Given /^(?:|I )fill in "(?P<text>[^"]*)" in WYSIWYG editor "(?P<iframe>[^"]*)"$/
     */
    public function iFillInInWYSIWYGEditor($text, $iframe)
    {
        $this->getMainContext()->getSession()->executeScript("document.getElementById('$iframe').contentDocument.body.innerHTML = '<p>".$text."</p>'");
        $this->getMainContext()->getSession()->switchToIFrame();
    }

    /**
     * Sets an id for the first iframe situated in the element specified by id.
     * Needed when wanting to fill in WYSIWYG editor situated in an iframe without identifier.
     *
     * @Given /^the iframe in element "(?P<element>[^"]*)" has id "(?P<id>[^"]*)"$/
     */
    public function theIframeInElementHasId($element_id, $iframe_id)
    {
        $function = <<<JS
(function(){
  var elem = document.getElementById("$element_id");
  var iframes = elem.getElementsByTagName('iframe');
  var f = iframes[0];
  f.id = "$iframe_id";
})()
JS;
        try
        {
            $this->getMainContext()->getSession()->executeScript($function);
        }
        catch (Exception $e)
        {
            throw new \Exception(sprintf('No iframe found in the element "%s" on the page "%s".', $element_id, $this->getMainContext()->getSession()->getCurrentUrl()));
        }
    }

    /**
     * @Given /^I switch to the iframe "([^"]*)"$/
     *
     * Drupal Media Module 7.x-1.3
     */
    public function iSwitchToTheIframe($name)
    {
        $this->getMainContext()->getSession()->switchToIFrame($name);
    }

    /**
     * @Given /^I leave the iframe$/
     *
     * Drupal Media Module 7.x-1.3
     */
    public function iLeaveTheIframe()
    {
        $this->getMainContext()->getSession()->switchToIFrame();
    }

    /**
     * @Given /^I click "([^"]*)" ajax link$/
     */
    public function iClickAjaxLink($link)
    {
        $link = $this->fixStepArgument($link);
        $this->getMainContext()->getSession()->getPage()->clickLink($link);
        $this->getMainContext()->getSession()->wait(2000, '(0 === jQuery.active)');
    }

    /**
     * @Given /^I wait for reload page$/
     */
    public function iWaitForReloadPage()
    {
        $this->getSession()->wait(5000, '(jQuery("input.email-tech-submit").length === 0)');
    }

    /**
     * Try to find the cell in a specific row under a specific header where our
     * selector exists. Needs improvements. :D
     *
     * @Then /^I should see the selector "([^"]*)" in the "([^"]*)" row under the "([^"]*)" header in the "([^"]*)" table$/
     */
    public function iShouldSeeTheSelectorInTheRowUnderTheHeaderInTheTable($value_selector, $row_value, $header_value, $table_selector) {
        $page  = $this->getSession()->getPage();
        $table = $page->find('css', $table_selector);
        $rows  = $table->findAll('css', 'tr');

        $index = 0;
        foreach ($rows as $row) {
            // Find the index of the cell where we will find the header.
            $headers = $row->findAll('css', 'th');
            if (!$headers) {
                continue;
            }

            foreach ($headers as $key => $value) {
                if (strpos($value->getText(), $header_value) === FALSE) {
                    continue;
                }

                $index = $key;
                break;
            }
        }

        // Find the cell where our selector exists.
        foreach ($rows as $row) {
            if (strpos($row->getText(), $row_value) === FALSE) {
                continue;
            }

            $cells = $row->findAll('css', 'td');
            foreach ($cells as $key => $cell) {
                if ($key !== $index) {
                    continue;
                }

                $ret = $cell->find('css', $value_selector);
                if ($ret) {
                    return;
                }
            }
        }

        throw new \Exception('Value not found.');
    }

    /**
     * Hook into node creation to test `@afterNodeCreate`
     *
     * @afterNodeCreate
     */
    public function alterNodeParameters(EntityEvent $event) {
        $node = $event->getEntity();
        if ($node->type == 'news') {
            $node_wrapper = entity_metadata_wrapper('node', $node);
            $term = $node_wrapper->field_news_category->value();
            if (is_object($term)) {
                $news_voc = taxonomy_vocabulary_machine_name_load('news_category');
                if ($term->vid != $news_voc->vid) {
                    $new_term = taxonomy_term_load_multiple(array(), array('vid' => $news_voc->vid, 'name' => $term->name));
                    if (!empty($new_term)) {
                        $new_term = reset($new_term);
                        $node_wrapper->field_news_category->set($new_term);
                        $node_wrapper->save();
                        $this->nodes[] = $node_wrapper->value();
                    }
                }
            }
        }
    }
}
