<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class PassportProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['openid', 'profile', 'email', 'workspaces:read'];

    protected $scopeSeparator = ' ';

    private string $hostUrl = 'https://idp.nevento.nl';

    private ?string $userInfoUrl = null;

    public function useHostUrl(?string $hostUrl): self
    {
        if (is_string($hostUrl) && trim($hostUrl) !== '') {
            $this->hostUrl = rtrim($hostUrl, '/');
        }

        return $this;
    }

    public function useUserInfoUrl(?string $userInfoUrl): self
    {
        if (is_string($userInfoUrl) && trim($userInfoUrl) !== '') {
            $this->userInfoUrl = $userInfoUrl;
        }

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->hostUrl.'/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->hostUrl.'/oauth/token';
    }

    protected function getTokenFields($code): array
    {
        $fields = parent::getTokenFields($code);

        if (! is_string($this->clientSecret) || trim($this->clientSecret) === '') {
            unset($fields['client_secret']);
        }

        return $fields;
    }

    protected function getUserByToken($token): array
    {
        $url = $this->userInfoUrl ?? ($this->hostUrl.'/api/user');

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept'        => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id'    => $user['id'] ?? null,
            'name'  => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }
}
