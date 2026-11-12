<?php

namespace App\Protocols;

class ShadowClash extends ClashMeta
{
    public $flags = ['shadowclash'];

    private const NOTICE_HEADER = 'X-ShadowClash-Notice';

    public function handle()
    {
        $response = parent::handle();
        $notice = $this->resolveNotice();

        if ($notice !== null) {
            $response->headers->set(self::NOTICE_HEADER, $this->encodeNotice($notice));
        }

        return $response;
    }

    /**
     * Resolve the currently active ShadowClash notice.
     *
     * TODO: Connect this method to Xboard's notice/config backend once the
     * storage and admin UI are decided. The backend should return at most one
     * active notice using the following shape:
     *
     * [
     *     'id' => 'provider-maintenance-2026-11',
     *     'message' => 'Some servers are under maintenance.',
     *     'startAt' => 1793466000,
     *     'endAt' => 1793552400,
     * ]
     *
     * Return null when no notice is active. Do not emit an empty header.
     */
    protected function resolveNotice(): ?array
    {
        return null;
    }

    private function encodeNotice(array $notice): string
    {
        $json = json_encode(
            $notice,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
