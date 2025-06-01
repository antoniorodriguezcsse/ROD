<?php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;

class CommunicationController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function sendDataToPlayersHUD(PDO $pdo, $playerUUID, $data)
    {
        try
        {
            // Fetch the player's HUD URL
            $existsQuery = "SELECT player_HUD_url FROM players WHERE player_uuid = :player_uuid";
            $existsStmt = $pdo->prepare($existsQuery);
            $existsStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
            $existsStmt->execute();

            if ($existsStmt->rowCount() == 0)
            {
                return new JsonResponse(404, "Player not found.");
            }

            $playerData = $existsStmt->fetch(PDO::FETCH_ASSOC);
            $playerHUDUrl = $playerData['player_HUD_url'];

            if (!$playerHUDUrl)
            {
                return new JsonResponse(400, "No HUD URL found for player with UUID: $playerUUID");
            }

            // Initialize cURL to send data to the player's HUD
            $ch = curl_init($playerHUDUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // Execute the request and fetch the response
            $response = curl_exec($ch);
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            // Check the response
            if ($httpStatusCode == 200)
            {
                // The request was successfully processed by the HUD server
                return new JsonResponse(200, "Data successfully sent to HUD.");
            } else
            {
                // The HUD server encountered an error processing the request
                return new JsonResponse(500, "HUD server responded with an error: HTTP $httpStatusCode");
            }

        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
        catch (Exception $e)
        {
            error_log("Error: " . $e->getMessage());
            return new JsonResponse(500, "Error sending data to HUD: " . $e->getMessage());
        }
    }

    public static function sendPostRequest($url, $data, $headers = [])
    {
        // Initialize cURL session
        $ch = curl_init($url);

        // The data should be a JSON string for 'Content-Type: application/json'
        $jsonData = json_encode($data);

        // Configure cURL options for a JSON POST request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response instead of outputting
        curl_setopt($ch, CURLOPT_POST, true); // Use POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // Attach JSON POST data

        // Add a 'Content-Type: application/json' header if not already set
        $hasContentType = false;
        foreach ($headers as $header)
        {
            if (stripos($header, 'Content-Type:') === 0)
            {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType)
        {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the POST request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch))
        {
            // Optionally handle the error according to your needs.
            // For this example, we'll just throw an exception.
            throw new Exception(curl_error($ch));
        }

        // Close cURL session
        curl_close($ch);

        // Return the response
        return $response;
    }

    public static function logErrorToDiscordByPlayerUUID($pdo, $playerUUID, $message, $fileName)
    {
        $legacyName = PlayerDataController::getPlayerLegacyName($pdo, $playerUUID);
        $fullMessage = "File: $fileName \nLegacy Name: $legacyName \nPlayer UUID: $playerUUID \nError:  $message";
        self::postToDiscordErrorLog($fullMessage);
    }

    public static function postToDiscordErrorLog($message)
    {
        $webhookUrl = "https://discord.com/api/webhooks/1259205692858826752/7wE0v2Gh7OnuuyO-i3BxY6AMKEk_Ni2_hFt899ehu4-BqPIoACtc-7O-3yBg_S2v6wdb";
        self::sendDiscordWebhook($webhookUrl, $message);
    }
    public static function postToDiscordCronLog($message)
    {
        $webhookUrl = "https://discord.com/api/webhooks/1272644174692483102/3xth43iGmGAYdS6delejghHi68WwOK1ZmjB8c8YFTafoF7kilwbYuYgxmuVtP1lH5C-Y";
        $response = self::sendDiscordWebhook($webhookUrl, $message);
        return $response;
    }

    public static function postToDiscordDeathLog($message)
    {
        $webhookUrl = "https://discord.com/api/webhooks/1273656407291465808/gAj11MkrKXf2FNzadrgAZnTDvL5NXZSVvLk2BiejXblOJGHxU7kpgQS-p9Qy3Zws6Obf";
        $response = self::sendDiscordWebhook($webhookUrl, $message);
        return $response;
    }

    public static function sendDiscordWebhook($webhookUrl, $message)
    {
        // Detect if $message is already a JSON string
        if (is_array($message) || is_object($message))
        {
            // Convert the array or object to a JSON string
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif (is_string($message))
        {
            // Check if the string is already a valid JSON
            json_decode($message);
            if (json_last_error() !== JSON_ERROR_NONE)
            {

                // If not valid JSON, assume it's plain text and wrap it in a JSON structure
                $message = json_encode([
                    "content" => $message,
                    "username" => "Dark Oracle",
                    "tts" => false,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        } else
        {
            return new JsonResponse(400, "Invalid message format: must be a string, array, or object.");
        }

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        if ($response === false)
        {
            $error = curl_error($ch);
            curl_close($ch);
            return new JsonResponse(500, "Failed to send request to Discord webhook: $error");
        }

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatusCode !== 200)
        {
            return new JsonResponse($httpStatusCode, "Discord webhook responded with an error: HTTP $httpStatusCode");
        }

        return new JsonResponse(200, "Message posted successfully to Discord.", $response);
    }

    public static function sendDataToURL($url, $data)
    {
        try
        {
            // Log the data to be sent for debugging
            //error_log("Data to be sent: " . print_r($data, true));

            // Ensure that the data is UTF-8 encoded
            $data = array_map(function ($item)
            {
                return is_string($item) ? mb_convert_encoding($item, 'UTF-8', 'UTF-8') : $item;
            }, $data);

            // Initialize cURL session
            $ch = curl_init($url);

            // Set cURL options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);

            // // Execute cURL session and fetch the response
            $response = curl_exec($ch);

            // Debug: Check CURL response
            error_log('CURL response: ' . $response);

            // Handle cURL errors
            if (curl_errno($ch))
            {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            // Close cURL session
            curl_close($ch);

            // Check if the received response matches the expected success message
            if ($response === "Batch received successfully.")
            {
                return ['status' => 200, 'message' => "Data sent and acknowledged by URL: $url", 'extra' => null];
            } else
            {
                return ['status' => 400, 'message' => "Unexpected response from URL: $response", 'extra' => null];
            }
        }
        catch (Exception $e)
        {
            error_log("Error in sendDataToURL: " . $e->getMessage());
            return ['status' => 500, 'message' => "Error: " . $e->getMessage(), 'extra' => null];
        }
    }

    public static function sendDataToHUDAndReceiveResponse(PDO $pdo, $playerUUID, $data)
    {
        try
        {
            // Fetch the player's HUD URL
            $existsQuery = "SELECT player_HUD_url FROM players WHERE player_uuid = :player_uuid";
            $existsStmt = $pdo->prepare($existsQuery);
            $existsStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
            $existsStmt->execute();
            if ($existsStmt->rowCount() == 0)
            {
                return new JsonResponse(404, "Player not found.");
            }
            $playerData = $existsStmt->fetch(PDO::FETCH_ASSOC);
            $playerHUDUrl = $playerData['player_HUD_url'];
            if (!$playerHUDUrl)
            {
                return new JsonResponse(400, "No HUD URL found for player with UUID: $playerUUID");
            }

            // Initialize cURL to send a request to the player's HUD
            $ch = curl_init($playerHUDUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // Log the data being sent
            //error_log("Sending data to HUD ($playerHUDUrl): " . json_encode($data));

            // Execute the request and fetch the response
            $response = curl_exec($ch);
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Log the response received from the HUD
            //error_log("Response from HUD ($playerHUDUrl): HTTP $httpStatusCode, Response: $response, Error: $error");

            // Check the response
            if ($httpStatusCode == 200)
            {
                // Return the response data
                return $response;
            } else
            {
                // The HUD server encountered an error processing the request
                return new JsonResponse(500, "HUD server responded with an error: HTTP $httpStatusCode, Error: $error");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
        catch (Exception $e)
        {
            error_log("Error: " . $e->getMessage());
            return new JsonResponse(500, "Error sending data to HUD: " . $e->getMessage());
        }
    }









    /**
     * Build a Discord "rich" embed array while skipping any empty fields.
     *
     * @param array $params An associative array that may contain any of the following:
     *                      [
     *                        'title'       => string,
     *                        'type'        => string,       // usually "rich" for webhook embeds
     *                        'description' => string,
     *                        'url'         => string,
     *                        'timestamp'   => string,       // ISO8601 timestamp, e.g. date('c')
     *                        'color'       => int,          // decimal color value (e.g., 0xFF0000)
     *                        'footer'      => [
     *                            'text'           => string,
     *                            'icon_url'       => string,
     *                            'proxy_icon_url' => string
     *                        ],
     *                        'image'       => [
     *                            'url'      => string,
     *                            'proxy_url'=> string,
     *                            'height'   => int,
     *                            'width'    => int
     *                        ],
     *                        'thumbnail'   => [
     *                            'url'      => string,
     *                            'proxy_url'=> string,
     *                            'height'   => int,
     *                            'width'    => int
     *                        ],
     *                        'video'       => [
     *                            'url'      => string,
     *                            'proxy_url'=> string,
     *                            'height'   => int,
     *                            'width'    => int
     *                        ],
     *                        'provider'    => [
     *                            'name'     => string,
     *                            'url'      => string
     *                        ],
     *                        'author'      => [
     *                            'name'           => string,
     *                            'url'            => string,
     *                            'icon_url'       => string,
     *                            'proxy_icon_url' => string
     *                        ],
     *                        'fields'      => [
     *                            [
     *                              'name'   => string,
     *                              'value'  => string,
     *                              'inline' => bool
     *                            ],
     *                            ...
     *                        ]
     *                      ]
     *
     * @return array A valid Discord embed array with only non-empty fields.
     */
    public static function buildDiscordEmbed(array $params): array
    {
        $embed = [];

        // "type" is usually "rich" for standard webhooks, but you can override if needed.
        $embed['type'] = $params['type'] ?? 'rich';

        // Top-level simple fields
        if (!empty($params['title']))
        {
            $embed['title'] = $params['title'];
        }
        if (!empty($params['description']))
        {
            $embed['description'] = $params['description'];
        }
        if (!empty($params['url']))
        {
            $embed['url'] = $params['url'];
        }
        if (!empty($params['timestamp']))
        {
            $embed['timestamp'] = $params['timestamp'];
        }
        if (!empty($params['color']))
        {
            // color is an integer. Example: 0xFF0000 for red.
            $embed['color'] = $params['color'];
        }

        // Footer
        if (!empty($params['footer']) && is_array($params['footer']))
        {
            $footer = [];
            if (!empty($params['footer']['text']))
            {
                $footer['text'] = $params['footer']['text'];
            }
            if (!empty($params['footer']['icon_url']))
            {
                $footer['icon_url'] = $params['footer']['icon_url'];
            }
            if (!empty($params['footer']['proxy_icon_url']))
            {
                $footer['proxy_icon_url'] = $params['footer']['proxy_icon_url'];
            }

            // Only add footer if it has at least 'text'
            if (!empty($footer['text']))
            {
                $embed['footer'] = $footer;
            }
        }

        // Image
        if (!empty($params['image']) && is_array($params['image']))
        {
            $image = [];
            if (!empty($params['image']['url']))
            {
                $image['url'] = $params['image']['url'];
            }
            if (!empty($params['image']['proxy_url']))
            {
                $image['proxy_url'] = $params['image']['proxy_url'];
            }
            if (!empty($params['image']['height']))
            {
                $image['height'] = $params['image']['height'];
            }
            if (!empty($params['image']['width']))
            {
                $image['width'] = $params['image']['width'];
            }

            // Only add image if at least "url" is not empty
            if (!empty($image['url']))
            {
                $embed['image'] = $image;
            }
        }

        // Thumbnail
        if (!empty($params['thumbnail']) && is_array($params['thumbnail']))
        {
            $thumbnail = [];
            if (!empty($params['thumbnail']['url']))
            {
                $thumbnail['url'] = $params['thumbnail']['url'];
            }
            if (!empty($params['thumbnail']['proxy_url']))
            {
                $thumbnail['proxy_url'] = $params['thumbnail']['proxy_url'];
            }
            if (!empty($params['thumbnail']['height']))
            {
                $thumbnail['height'] = $params['thumbnail']['height'];
            }
            if (!empty($params['thumbnail']['width']))
            {
                $thumbnail['width'] = $params['thumbnail']['width'];
            }

            if (!empty($thumbnail['url']))
            {
                $embed['thumbnail'] = $thumbnail;
            }
        }

        // Video
        // Note: Discord typically sets this automatically from a link in the embed,
        // so generally you don't supply video yourself. But we'll allow it:
        if (!empty($params['video']) && is_array($params['video']))
        {
            $video = [];
            if (!empty($params['video']['url']))
            {
                $video['url'] = $params['video']['url'];
            }
            if (!empty($params['video']['proxy_url']))
            {
                $video['proxy_url'] = $params['video']['proxy_url'];
            }
            if (!empty($params['video']['height']))
            {
                $video['height'] = $params['video']['height'];
            }
            if (!empty($params['video']['width']))
            {
                $video['width'] = $params['video']['width'];
            }

            // Discord won't always display custom video data, but we add it if present
            if (!empty($video['url']))
            {
                $embed['video'] = $video;
            }
        }

        // Provider
        // Like video, the provider field is usually set automatically by Discord,
        // but we can still include it if desired.
        if (!empty($params['provider']) && is_array($params['provider']))
        {
            $provider = [];
            if (!empty($params['provider']['name']))
            {
                $provider['name'] = $params['provider']['name'];
            }
            if (!empty($params['provider']['url']))
            {
                $provider['url'] = $params['provider']['url'];
            }

            if (!empty($provider))
            {
                $embed['provider'] = $provider;
            }
        }

        // Author
        if (!empty($params['author']) && is_array($params['author']))
        {
            $author = [];
            if (!empty($params['author']['name']))
            {
                $author['name'] = $params['author']['name'];
            }
            if (!empty($params['author']['url']))
            {
                $author['url'] = $params['author']['url'];
            }
            if (!empty($params['author']['icon_url']))
            {
                $author['icon_url'] = $params['author']['icon_url'];
            }
            if (!empty($params['author']['proxy_icon_url']))
            {
                $author['proxy_icon_url'] = $params['author']['proxy_icon_url'];
            }

            // Only add author if "name" exists
            if (!empty($author['name']))
            {
                $embed['author'] = $author;
            }
        }

        // Fields
        if (!empty($params['fields']) && is_array($params['fields']))
        {
            $fields = [];
            foreach ($params['fields'] as $field)
            {
                // Discord requires 'name' and 'value' to be non-empty
                if (!empty($field['name']) && !empty($field['value']))
                {
                    $fieldObj = [
                        'name' => $field['name'],
                        'value' => $field['value'],
                    ];
                    if (isset($field['inline']))
                    {
                        $fieldObj['inline'] = (bool) $field['inline'];
                    }
                    $fields[] = $fieldObj;
                }
            }

            if (!empty($fields))
            {
                $embed['fields'] = $fields;
            }
        }

        return $embed;
    }

}
