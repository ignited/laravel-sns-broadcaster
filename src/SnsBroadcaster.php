<?php

namespace Ignited\LaravelSnsBroadcaster;

use Aws\Sns\SnsClient;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Arr;

class SnsBroadcaster implements Broadcaster
{
    /**
     * @var SnsClient
     */
    protected $snsClient;

    /**
     * @var string
     */
    protected $topicArn;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @var bool
     */
    protected $fifo;

    /**
     * SnsBroadcaster constructor.
     *
     * @param  SnsClient  $client
     * @param  string  $topicArn
     * @param  string  $suffix
     */
    public function __construct(SnsClient $client, string $topicArn, string $suffix, bool $fifo = false)
    {
        $this->snsClient = $client;
        $this->topicArn = $topicArn;
        $this->suffix = $suffix;
        $this->fifo = $fifo;
    }

    /**
     * @inheritDoc
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $message = [
            'TopicArn' => $this->topicName($channels),
            'Subject' => $event,
            'Message' => json_encode(Arr::except($payload, 'socket')),
        ];

        if ($this->fifo) {
            $params['MessageDeduplicationId'] = $this->getDeduplicationId($payload, $event);
            $params['MessageGroupId'] = $this->getGroupId($channels);
        }

        $this->snsClient->publish($message);
    }

    /**
     * @inheritDoc
     */
    public function auth($request)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function validAuthenticationResponse($request, $result)
    {
        return true;
    }

    /**
     * Returns topic name built for sns
     *
     * @param  array  $channels
     *
     * @return string
     */
    private function topicName(array $channels): string
    {
        return $this->topicArn.Arr::first($channels).$this->suffix;
    }

    private function getDeduplicationId(array $payload, $event): string
    {
        // Use a combination of event type and a unique payload attribute or simply generate a UUID
        return md5(json_encode($payload) . $event);
    }

    private function getGroupId(array $channels): string
    {
        // Usually, a consistent value per channel or event type
        return 'group-' . Arr::first($channels);
    }
}
