<?php

namespace App\Auth;

use Illuminate\Auth\TokenGuard;
use Illuminate\Http\Request;

class BearerTokenGuard extends TokenGuard
{
    /**
     * Get the token for the current request.
     *
     * @return string|null
     */
    public function getTokenForRequest()
    {
        $token = $this->request->bearerToken();
        
        if (empty($token)) {
            $token = $this->request->query($this->inputKey);
        }

        if (empty($token)) {
            $token = $this->request->input($this->inputKey);
        }

        if (empty($token)) {
            $token = $this->request->header($this->inputKey);
        }

        if (empty($token)) {
            $token = $this->request->server('HTTP_AUTHORIZATION');
            if ($token && strpos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            } else {
                $token = null;
            }
        }

        return $token;
    }
}