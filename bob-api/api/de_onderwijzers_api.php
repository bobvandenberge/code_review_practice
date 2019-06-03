<?php namespace api;

use ErrorException;

/**
 * Class DeOnderwijzers
 *
 * Wrapper for de API die de onderwijzers beschikbaar hebben gesteld.
 *
 * Documentatie die goed is om te lezen:
 * - https://graphql.org/learn/queries
 * - https://graphql.org/learn/queries/#variables
 *
 * Als je debug logging aan wilt hebben, zet dan $DEBUG_ENABLE naar true
 *
 * GraphiQl docs: http://api-test.edwh.nl/graphql/?api_key=IBFiOq8Fyoxcqxo4gyDT7pBXzC0REahqcOXu1Nql
 *
 * @package api
 */
class DeOnderwijzers
{
    public static $DEBUG_ENABLED = false;

    /**
     * The API key to use during the connection
     */
    const API_KEY = "IBFiOq8Fyoxcqxo4gyDT7pBXzC0REahqcOXu1Nql";

    /**
     * The base url of the external connection
     */
    const BASE_URL = "http://api-test.edwh.nl/graphql/?api_key=";

    /**
     * Session token for the application. This should only be used for non-user specific actions.
     * Once we have a user, his/her sessionToken should be used for subsequent requests.
     */
    private $applicationSessionToken;

    /**
     * DeOnderwijzers constructor.
     *
     * Create a new session token that will be used for all subsequent requests
     * @throws ErrorException
     */
    public function __construct()
    {
        $this->applicationSessionToken = $this->generateSessionToken();
    }

    /**
     * Get information about the currently logged in user
     * @param string $sessionToken Session token for a user
     * @return array
     * @throws ErrorException
     */
    public function getMe(string $sessionToken): array
    {
        $this->logDebug("Executing getMe() query");

        $query = <<<GRAPHQL
        query RootQuery(\$sessionToken: UUID) {
            viewer(sessionToken: \$sessionToken) {
                me {
                  id,
                  email,
                  apiToken,
                  avatar
                }
            }
        }
GRAPHQL;

        $result = $this->executeQuery($query, array("sessionToken" => $sessionToken));

        $this->logDebug("Execution of getMe() finished");

        return $result['data'];
    }

    /**
     * Validate the given credentials
     * @param string $email The email of the user
     * @param string $password The password of the user
     * @return array
     * @throws ErrorException
     */
    public function validateCredentials(string $email, string $password): array
    {
        $this->logDebug("Executing validateCredentials() query");

        $query = <<<GRAPHQL
        query RootQuery(\$sessionToken: UUID, \$email: String, \$passwordHash: String, \$hardware: JSONString) {
           viewer(sessionToken: \$sessionToken) {
            validateCredentials(email: \$email, passwordHash: \$passwordHash, hardware: \$hardware) {
              ok,
              feedback,
              user {
                id,
                email,
                name,
                permalink,
                avatar
              },
              sessionToken
            }
          }
        }
GRAPHQL;

        $passwordHash = md5($password);

        $result = $this->executeQuery($query, array(
            "sessionToken" => $this->applicationSessionToken,
            "email" => $email,
            "passwordHash" => $passwordHash,
            "hardware" => "{\"test\": 1}"
        ));

        $this->logDebug("Execution of validateCredentials() finished");

        return $result['data']['viewer']['validateCredentials'];
    }

    /**
     * Generate a new session token
     * @throws ErrorException
     */
    private function generateSessionToken(): string
    {
        $this->logDebug("Executing generateSessionToken() query");

        $query = <<<GRAPHQL
        query RootQuery(\$hardware: JSONString!, \$platform: Platform!) {
            newSessionToken(hardware: \$hardware, platform: \$platform)
        }
GRAPHQL;

        $result = $this->executeQuery($query, array("platform" => "DEBUG", "hardware" => "{\"test\": 1}"));

        $this->logDebug("Execution of generateSessionToken() finished");

        return $result['data']['newSessionToken'];
    }

    /**
     * Execute the given query
     *
     * @param string $query
     * @param array $variables
     * @return array
     * @throws ErrorException
     */
    private function executeQuery(string $query, array $variables = []): array
    {
        $headers = ['Content-Type: application/json'];

        if (false === $data = @file_get_contents(self::BASE_URL . self::API_KEY, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode(['query' => $query, 'variables' => $variables]),
                ]
            ]))) {
            $error = error_get_last();
            throw new ErrorException($error['message'], $error['type']);
        }
        $result = json_decode($data, true);

        if ($result['errors']) {
            $this->logDebug("Got error while executing query: [" . $query . "]");
            $this->logDebug($result);

            throw new ErrorException("Failed to execute api request: [" . $result['errors'][0]["message"] . "']");
        }

        return $result;
    }

    /**
     * Log something if debug is enabled
     */
    private function logDebug($input)
    {
        if (self::$DEBUG_ENABLED) {
            if (is_string($input)) {
                $dateTime = new \DateTime();
                echo __FILE__ . " [" . $dateTime->format("H:i:s:u") . "]: " . $input . "\n";
            } else {
                var_dump($input);
                echo "\n";
            }
        }
    }
}
