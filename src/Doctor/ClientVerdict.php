<?php

namespace MobileStock\Gatekeeper\Doctor;

enum ClientVerdict
{
    /** users-api authenticated the client and only rejected the deliberately invalid grant. */
    case ACCEPTED;

    /** users-api answered invalid_client: wrong ID or secret, revoked client, or unregistered redirect URI. */
    case REJECTED;
}
