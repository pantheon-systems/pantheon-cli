<?php

namespace Pantheon\Terminus\Collections;

use Pantheon\Terminus\Models\Site;
use Pantheon\Terminus\Session\SessionAwareInterface;
use Pantheon\Terminus\Session\SessionAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class Sites
 * @package Pantheon\Terminus\Collections
 */
class Sites extends APICollection implements SessionAwareInterface
{
    use SessionAwareTrait;

    const PRETTY_NAME = 'sites';

    /**
     * @var string
     */
    protected $collected_class = Site::class;

    /**
     * Creates a new site
     *
     * @param string[] $params Options for the new site, elements as follow:
     *   string label The site's human-friendly name
     *   string site_name The site's name
     *   string organization_id Organization to which this site belongs' UUID
     * @return Workflow
     */
    public function create($params = [])
    {
        return $this->getUser()->getWorkflows()->create('create_site', compact('params'));
    }

    /**
     * Creates a new site for migration
     *
     * @param string[] $params Options for the new site, elements as follow:
     *   string label The site's human-friendly name
     *   string site_name The site's name
     *   string organization_id Organization to which this site belongs' UUID
     *   string type Workflow type for imports
     * @return Workflow
     */
    public function createForMigration($params = [])
    {
        return $this->getUser()->getWorkflows()->create('create_site_for_migration', compact('params'));
    }

    /**
     * Fetches model data from API and instantiates its model instances
     *
     * @param array $arg_options Options to change the requests made. Elements as follow:
     *        string  org_id    UUID of the organization to retrieve sites for
     *        boolean team_only True to only retrieve team sites
     * @return Sites
     */
    public function fetch(array $arg_options = [])
    {
        $default_options = [
            'org_id' => null,
            'team_only' => false,
        ];
        $options = array_merge($default_options, $arg_options);

        $sites = [];
        if (is_null($options['org_id'])) {
            $sites[] = $this->getUser()->getSites();
        }

        if (!$options['team_only']) {
            $memberships = $this->getUser()->getOrganizationMemberships()->fetch()->all();
            if (!is_null($org_id = $options['org_id'])) {
                $memberships = array_filter($memberships, function ($membership) use ($org_id) {
                    return $membership->id == $org_id;
                });
            }
            if (is_array($memberships)) {
                foreach ($memberships as $membership) {
                    if ($membership->get('role') != 'unprivileged') {
                        $sites[] = $membership->getOrganization()->getSites();
                    }
                }
            }
        }

        $merged_sites = [];
        foreach ($sites as $site_group) {
            foreach ($site_group as $site) {
                if (!isset($merged_sites[$site->id])) {
                    $merged_sites[$site->id] = $site;
                } else {
                    $merged_sites[$site->id]->memberships[] = $site->memberships[0];
                }
            }
        }
        $this->models = $merged_sites;

        return $this;
    }

    /**
     * Filters the members of this collection by their names
     *
     * @param string $regex Non-delimited PHP regex to filter site names by
     * @return Sites
     */
    public function filterByName($regex = '(.*)')
    {
        return $this->filterByRegex('name', $regex);
    }

    /**
     * Filters an array of sites by the plan name
     *
     * @param string $plan_name Name of the plan to filter by
     * @return Sites
     */
    public function filterByPlanName($plan_name)
    {
        $plan_name = strtolower($plan_name);
        return $this->filter(function ($model) use ($plan_name) {
            return (strtolower($model->get('plan_name')) === $plan_name);
        });
    }

    /**
     * Filters an array of sites by whether the user is an organizational member
     *
     * @param string $owner_uuid UUID of the owning user to filter by
     * @return Sites
     */
    public function filterByOwner($owner_uuid)
    {
        return $this->filter(function ($model) use ($owner_uuid) {
            return ($model->get('owner') == $owner_uuid);
        });
    }

    /**
     * Filters sites list by tag
     *
     * @param string $tag A tag to filter by
     * @return Sites
     */
    public function filterByTag($tag)
    {
        return $this->filter(function ($site) use ($tag) {
            return $site->tags->has($tag);
        });
    }

    /**
     * Filters sites list by upstream
     *
     * @param string $upstream_id An upstream to filter by
     * @return Sites
     */
    public function filterByUpstream($upstream_id)
    {
        $upstream_id = strtolower($upstream_id);
        return $this->filter(function ($model) use ($upstream_id) {
            return in_array($upstream_id, $model->getUpstream()->getReferences());
        });
    }

    /**
     * Retrieves the site of the given UUID or name
     *
     * If the site list has already been fetched then this function will search for the site in the fetched list.
     * If no sites have been fetched yet then it will query the API. Use caution when calling this function after
     * a manual fetch as it may be just searching a subset of the user's sites.
     *
     * @param string $id UUID or name of desired site
     * @return Site
     * @throws TerminusException
     */
    public function get($id)
    {
        try {
            $uuid = $this->findUUIDByNameOrUUID($id);
            $site = $this->models[$uuid] ?? null;
            if (!$site instanceof Site) {
                $injectionNickname = $id . "-" . \uniqid();
                $this->getContainer()->add($injectionNickname, $this->collected_class)
                    ->addArguments(
                        [
                            (object)['id' => $uuid],
                            ['id' => $uuid, 'collection' => $this]
                        ]
                    );
                $site = $this->getContainer()->get($injectionNickname);
                $site->fetch();
                $this->models[$uuid] = $site;
            }
        } catch (\Exception $e) {
            throw new TerminusException(
                'Could not locate a site your user may access identified by {id}.',
                compact('id'),
                1
            );
        }
        return $site;
    }

    /**
     * Determines whether a given site name is taken or not.
     *
     * @param string $name Name of the site to look up
     * @return boolean
     */
    public function nameIsTaken($name) : bool
    {
        return (bool) $this->findUUIDByName($name) ?? false;
    }

    /**
     * Looks up a site's UUID by its name.
     *
     * @param string $name Name of the site to look up
     * @return string
     */
    protected function findUUIDByName($name): ?string
    {
        $response = $this->request()->request(
            "site-names/$name",
            ['method' => 'get',]
        );

        return $response['data']->id ?? null;
    }

    /**
     * Looks up a site's UUID by its name.
     *
     * @param string $id Name of the site to look up
     * @return string
     */
    protected function findUUIDByNameOrUUID($id): ?string
    {
        // If it LOOKS like a uuid, then we assume it is. Since a user is unlikely to name a site with this exact
        // pattern this is a reasonably good test.
        if ($this->isUUID($id)) {
            return $id;
        }
        return $this->findUUIDByName($id);
    }

    /**
     * Determine if the given string looks like a valid uuid.
     *
     * This is not an exact test for uuids but it matches the general pattern:
     *  xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     * where x is any hexidecimal character. This is close enough for our purposes.
     *
     * @param $id
     * @return bool
     */
    protected function isUUID($id): bool
    {
        return preg_match('/[a-f0-9]{8}-([a-f0-9]{4}-){3}[a-f0-9]{12}/', strtolower($id));
    }
}
