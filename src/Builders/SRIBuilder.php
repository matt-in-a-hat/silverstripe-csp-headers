<?php


namespace Firesphere\CSPHeaders\Builders;

use Exception;
use Firesphere\CSPHeaders\Extensions\ControllerCSPExtension;
use Firesphere\CSPHeaders\Models\SRI;
use Firesphere\CSPHeaders\View\CSPBackend;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;

class SRIBuilder
{
    use Configurable;

    /**
     * An array of javascript or css files to skip applying SRI to.
     * Files only need to start with the configured value, e.g. if this array contains
     * 'https://example.com' then all scripts from that site will be skipped.
     * @var array
     */
    private static $skip_domains = [];

    /**
     * @param $file
     * @param array $htmlAttributes
     * @return array
     * @throws ValidationException
     * @throws Exception
     */
    public function buildSRI($file, array $htmlAttributes): array
    {
        $skipFiles = $this->config()->get('skip_domains');
        foreach ($skipFiles as $filename) {
            if (strpos($file, $filename) === 0) {
                return $htmlAttributes;
            }
        }
        // Remove all existing SRI's, if an update is needed
        if ($this->shouldUpdateSRI()) {
            DB::query('TRUNCATE `SRI`');
        }
        $sri = SRI::findOrCreate($file);

        $request = Controller::curr()->getRequest();
        $cookieSet = ControllerCSPExtension::checkCookie($request);

        // Don't write integrity in dev, it's breaking build scripts
        if ($sri->SRI && (Director::isLive() || $cookieSet)) {
            $htmlAttributes['integrity'] = sprintf('%s-%s', CSPBackend::SHA384, $sri->SRI);
            $htmlAttributes['crossorigin'] = Director::is_site_url($file) ? '' : 'anonymous';
        }

        return $htmlAttributes;
    }

    /**
     * @return bool
     */
    private function shouldUpdateSRI(): bool
    {
        // Is updateSRI requested?
        return (Controller::curr()->getRequest()->getVar('updatesri') &&
            // Does the user have the powers
            ((Security::getCurrentUser() && Security::getCurrentUser()->inGroup('administrators')) ||
                // OR the site is in dev mode
                Director::isDev()));
    }
}
