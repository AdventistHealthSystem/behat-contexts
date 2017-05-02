<?php

namespace Ahs\Behat;

use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Exception\ExpectationException;

/**
 * Defines application features from the specific context.
 */
class BehatContext extends MinkContext
{
    const ERR_IMG_SRC_INCORRECT = 'The element [%s] does not have the src [%s]';
    const ERR_ELEMENT_NOT_FOUND = 'The element [%s] could not be found';
    const ERR_URL_BAD_STATUS    = 'The url [%s] does not return a 200 status code (got [%d])';
    const MSG_LINK_STATUS_CODE  = '[%d] [%s]';
    const MSG_LINK_SKIPPED = '[%s] skipped - %s';
    const MSG_CHECK_COUNT = "At [%s] - \n\t[%d] skipped \n\t[%d] invalid \n\t[%d] failed \n\t[%d] passed\n\t";

    protected $visited = array();

    protected $results;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {

    }


    protected function resetCounts()
    {
        $this->results = array(
            'skipped' => 0,
            'invalid' => 0,
            'success' => 0,
            'failed'  => 0,
            'failedUrls' => array()
        );
    }

    protected function getVisited()
    {
        return $this->visited;
    }

    protected function addVisited($url)
    {
        $this->visited[] = $url;
        return $this;
    }

    protected function hasVisited($url)
    {
        if (in_array($url, $this->visited)) {
            return true;
        }
        return false;
    }

    protected function tryVisit($url)
    {
        if (! $this->hasVisited($url)) {
            $this->addVisited($url);
            $this->visitUrl($url);
        } else {
          $this->results['skipped']++;
        }
    }

    /**
     * Checks that an image tag has a specific url value
     *
     * @Then I should see image :arg1 with src :arg2
     */
    public function assertImageSrcValue($selector, $url)
    {
        $session = $this->getSession();
        $page    = $session->getPage();
        $image   = $page->find('css', $selector);

        $this->checkForElement($image, $selector);

        if ($image->getAttribute('src') != $url) {
            throw new ExpectationException(
                sprintf(self::ERR_IMG_SRC_INCORRECT, $selector, $url),
                $session
            );
        }
    }

    /**
     * @Then all links should work
     */
    public function assertAllLinksWork()
    {
        $this->resetCounts();
        $url = $this->getSession()->getCurrentUrl();
        $elements = $this->getSession()->getPage()->findAll('css', 'a');
        $this->checkUrlsByAttribute($url, 'href', $elements);

        $this->visit($url);

        $this->reportResults();

    }

    /**
     * @Then all images should work
     */
    public function assertAllImagesShow()
    {
        $this->resetCounts();
        $url = $this->getSession()->getCurrentUrl();
        $elements = $this->getSession()->getPage()->findAll('css', 'img');
        $this->checkUrlsByAttribute($url, 'src', $elements);

        $this->visit($url);

        $this->reportResults();
    }

    protected function reportResults()
    {
        if ($this->results['failed'] > 0) {
            foreach ($this->results['failedUrls'] as $failedUrl => $status) {
              echo sprintf(self::MSG_LINK_STATUS_CODE, $status, $failedUrl), PHP_EOL;
            }
            throw new Exception('Some links did not return a 200');
        }
    }

    /**
     * @When I submit the form :arg1
     */
    public function submitForm($selector)
    {
        $session = $this->getSession();
        $form    = $session->getPage()->find('css', $selector);

        $this->checkForElement($form, $selector);
        $form->submit();
    }

    protected function checkUrlsByAttribute($root, $attribute, $elements = array())
    {
        $this->visit($root);
        $location = $this->getSession()->getCurrentUrl();
        $page = $this->getSession()->getPage();
        $count = count($elements);
        foreach ($elements as $element) {
            $this->visitUrlByElementAttribute($element, $root, $attribute);
        }

        $this->reportValidUrls($location);
    }

    protected function visitUrlByElementAttribute($element, $root, $attribute)
    {
        try {
            $this->visit($root);
            $url = $element->getAttribute($attribute);
            $this->tryVisit($url);
        } catch (Exception $exception) {
            error_log($this->getSession()->getCurrentUrl());
            error_log($element->getTagName());
            error_log('FeatureContext::checkUrlsByAttribute - ' . $exception->getMessage());
        }
    }

    protected function visitUrl($url)
    {
        if (!$this->isValidUrl($url)) {
            echo sprintf(self::MSG_LINK_SKIPPED, $url, 'invalid url'), PHP_EOL;
            return;
        }

        if ($url[0] == '/') {
            $currentUrl = $this->getSession()->getCurrentUrl();
            $parsedUrl = parse_url($currentUrl);
            $client = $this->getSession()->getDriver()->getClient()->getClient();
            $result = $client->request('GET', $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $url);

            $status = $result->getStatusCode();

        } else {
            $this->visitPath($url);
            $session = $this->getSession();
            $status = $session->getStatusCode();
        }


        if ($status !== 200) {
            $this->results['failed']++;
            $this->results['failedUrls'][$url] = $status;
        } else {
            $this->results['success']++;
        }
    }

    protected function isValidUrl($url = '')
    {
        if (!strlen($url)) {
            return false;
        }

        $bad = array(
            'donation.floridahospital.com',
            'mailto:',
            'tel:',
        );

        foreach ($bad as $path) {
            if (strpos($url, $path) !== false) {
                $this->results['invalid']++;
                return false;
            }
        }

        return true;
    }

    protected function checkForElement($element, $selector)
    {
        if (! $form) {
            throw new ExpectationException(
                sprintf(self::ERR_ELEMENT_NOT_FOUND, $selector),
                $this->getSession()
            );
        }
    }

    protected function reportValidUrls($location) {
      echo sprintf(
            self::MSG_CHECK_COUNT,
            $location,
            $this->results['skipped'],
            $this->results['invalid'],
            $this->results['failed'],
            $this->results['success']
        );
    }
}
