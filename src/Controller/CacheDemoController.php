<?php

namespace Drupal\my_module\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use \GuzzleHttp\Client;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cache demo main page.
 */
class CacheDemoController extends ControllerBase
{

    /**
     * @var CacheBackendInterface
     */
    protected $cacheBackend;

    /**
     * Class constructor.
     */
    public function __construct(CacheBackendInterface $cacheBackend)
    {
        $this->cacheBackend = $cacheBackend;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('cache.default')
        );
    }

    /**
     * Renders the page for the cache demo.
     */
    public function index(Request $request)
    {
        $output = array();

        // get query from url
        $clear = $request->query->get('clear');

        // request to clear cache
        if ($clear) {
            $this->clearPosts();
        }

        // normal request
        if (!$clear) {

            $start_time = microtime(TRUE);
            // return data from cache
            $data = $this->loadPosts();
            $end_time = microtime(TRUE);

            $duration = $end_time - $start_time;
            $reload = $data['means'] == 'API' ? 'Reload the page to retrieve the posts from cache and see the difference.' : '';

            // message to show to user
            $output['duration'] = array(
                '#type' => 'markup',
                '#prefix' => '<div>',
                '#suffix' => '</div>',
                '#markup' => $this->t(
                    'The duration for loading the posts has been @duration ms using the @means. @reload',
                    array(
                        '@duration' => number_format($duration * 1000, 2),
                        '@means' => $data['means'],
                        '@reload' => $reload
                    )
                ),
            );
        }

        // check if data returned from cache
        if ($cache = $this->cacheBackend->get('cache_demo_posts') && $data['means'] == 'cache') {
            $url = Url::fromRoute('my_module_page', ['clear' => true]);

            // output to clear cache
            $output['clear'] = array(
                '#type' => 'markup',
                '#markup' => Link::fromTextAndUrl('Clear the cache and try again', $url)->toString(),
                // '#markup' => 'Clear the cache and try again',

            );
        }

        // just for the first time when cache 
        if (!$cache = $this->cacheBackend->get('cache_demo_posts')) {
            $url = Url::fromRoute('my_module_page', ['clear' => false]);

            // get data from api and the save them in cache
            $output['populate'] = array(
                '#type' => 'markup',
                '#markup' => Link::fromTextAndUrl('Try loading again to query the API and re-populate the cache', $url)->toString(),
                //'#markup' => 'Try loading again to query the API and re-populate the cache',
            );
        }



        // https://www.drupal.org/docs/8/api/cache-api/access-checkers-cacheability
        // $access_result = AccessResult::allowedIf($node->isPublished())->addCacheableDependency($node);
        // Access result depends on a property of the object that might change: it is a cacheable dependency.
        //  ->addCacheableDependency($node);

        return $output;
    }


    /**
     * Renders the page for the cache demo.
     */
    public function invalidatemycache(Request $request)
    {
        if ($cache = $this->cacheBackend->get('cache_demo_posts')) {
            //Cache::invalidateTags(['tag_1', 'tag_2', 'tag_3']);
            Cache::invalidateTags(['tag_1', 'tag_2', 'tag_3']);
            $result = "invalidate cache with tags";
        } else {
            $result = "There aren't any data in the cache";
        }
        return [
            '#type' => 'markup',
            '#prefix' => '<div>',
            '#suffix' => '</div>',
            '#markup' => $result,
        ];
    }

    /**
     * Loads a bunch of dummy posts from cache or API
     * @return array
     */
    private function loadPosts()
    {
        // check if there is a cache by get(cid)
        if ($cache = $this->cacheBackend->get('cache_demo_posts')) {

            \Drupal::messenger()->addStatus('From cache');

            // return cached data
            return array(
                'data' => $cache->data,
                'means' => 'cache',
            );
        } else {
            $guzzle = new Client();
            $response = $guzzle->get('http://jsonplaceholder.typicode.com/posts');
            $posts = json_decode($response->getBody());

            \Drupal::messenger()->addStatus('From guzzle');

            // save data in cache by set(cid,data,time,tags)
            // CacheBackendInterface::CACHE_PERMANENT forever
            $this->cacheBackend->set('cache_demo_posts', $posts, \Drupal::time()->getRequestTime() + (60), ['tag_1', 'tag_2', 'tag_3']);
            return array(
                'data' => $posts,
                'means' => 'API',
            );
        }
    }

    /**
     * Clears the posts from the cache.
     */
    function clearPosts()
    {
        // check if there is a cache named cache_demo_posts by get(cid)
        if ($cache = $this->cacheBackend->get('cache_demo_posts')) {

            // clear the cache by delete(cid)
            $this->cacheBackend->delete('cache_demo_posts');
            \Drupal::messenger()->addStatus('Posts have been removed from cache.');
        } else {
            \Drupal::messenger()->addError('No posts in cache.');
        }
    }
}
