<?php

namespace Drupal\farm_nfa\Service;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to handle GFW API interactions.
 */
class GfwApiService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new GfwApiService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   The HTTP client factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientFactory $clientFactory, LoggerChannelFactoryInterface $loggerFactory) {
    $this->configFactory = $configFactory;
    $this->httpClient = $clientFactory->fromOptions();
    $this->logger = $loggerFactory->get('farm_nfa');
  }

  /**
   * Generates a GFW API key.
   *
   * @param string $endpoint
   *   The API endpoint to call.
   * @param array $options
   *   An optional array of options to pass to the HTTP client.
   *
   * @return string|null
   *   The API key as a string, or NULL on failure.
   */
  public function generateGfwApiKey(string $endpoint, array $options = []) {
    try {
      $config = $this->configFactory->getEditable('system.site');
      $currentDate = new DateTimePlus();
      $currentDate = $currentDate->modify('+7 days');

      $gfwApiKey = $config->get('farm_nfa.gfw_api_key') ?? '';
      $gfwApiKeyExpiryDate = new DateTimePlus($config->get('farm_nfa.gfw_api_key_expiry_date') ?? 'now');
      $key_is_valid = ($gfwApiKeyExpiryDate > $currentDate) && !empty($gfwApiKey);
      if (!empty($options['username']) && !empty($options['password']) && (!$key_is_valid)) {
        // Generate Auth Token.
        $response = $this->httpClient->post($endpoint . '/auth/token', [
          'form_params' => [
            'username' => $options['username'],
            'password' => $options['password'],
          ],
          'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
          ],
        ]);
        $responseData = json_decode($response->getBody(), TRUE);
        $accessToken = $responseData['data']['access_token'];
        // Generate a new API key.
        $gfwApiKeyResponse = $this->httpClient->post($endpoint . '/auth/apikey', [
          'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'alias' => 'nfa-api-key-' . time(),
            'organization' => 'nfa',
            'email' => $options['username'],
          ],
        ]);
        $gfwApiKeyData = json_decode($gfwApiKeyResponse->getBody(), TRUE);
        $newGfwApiKey = $gfwApiKeyData['data']['api_key'];
        $expiryDate = $gfwApiKeyData['data']['expires_on'];
        if (empty($gfwApiKey)) {
          $gfwApiKey = $newGfwApiKey;
        }
        // Save the new key and expiry date.
        $config->set('farm_nfa.gfw_api_key', $newGfwApiKey)->save();
        $config->set('farm_nfa.gfw_api_key_expiry_date', $expiryDate)->save();
      }

      return $gfwApiKey;
    }
    catch (\Exception $e) {
      $this->logger->error('GFW API call failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

}
