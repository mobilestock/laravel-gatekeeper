<?php

namespace MobileStock\Gatekeeper\Doctor;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;
use Throwable;

/**
 * Base of the users-api diagnostics. Details never carry a response body, a secret or a token:
 * only the HTTP status, the OAuth error code and messages written here.
 */
abstract class UsersDiagnostic extends Diagnostic
{
    public const TIMEOUT_IN_SECONDS = 10;

    public const CONNECT_TIMEOUT_IN_SECONDS = 5;

    public string $group = 'users-api';

    abstract protected function probe(): void;

    abstract protected function remediation(): string;

    protected function messages(): array
    {
        $messages = [
            'accepted' => "{$this->name}: a users-api aceitou a configuração do ambiente.",
            'rejected' => Message::make(
                summary: "{$this->name}: a users-api recusou a configuração do ambiente ou não respondeu.",
                remediation: $this->remediation()
            ),
            'skipped' => "{$this->name}: não verificado.",
        ];

        return $messages;
    }

    public function check(): DiagnosticResult
    {
        try {
            $this->probe();
        } catch (DiagnosticSkipped $exception) {
            $result = $this->skip('skipped')->withDetails($exception->getMessage());

            return $result;
        } catch (ConnectionException) {
            $timeout = self::TIMEOUT_IN_SECONDS;
            $result = $this->fail('rejected')->withDetails("Sem resposta em {$timeout} s ou conexão recusada.");

            return $result;
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $result = $this->fail('rejected')->withDetails("HTTP $status.");

            return $result;
        } catch (Throwable $exception) {
            $result = $this->fail('rejected')->withDetails($exception->getMessage());

            return $result;
        }

        $result = $this->pass('accepted');

        return $result;
    }
}
