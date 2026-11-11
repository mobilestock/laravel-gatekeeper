<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\DiagnosticSkipped;
use MobileStock\Gatekeeper\Doctor\UsersDiagnostic;

function usersDiagnosticThatThrows(?Throwable $exception): UsersDiagnostic
{
    $diagnostic = new class ($exception) extends UsersDiagnostic {
        public string $name = 'Teste';

        public function __construct(private ?Throwable $exception)
        {
            parent::__construct();
        }

        protected function probe(): void
        {
            if ($this->exception !== null) {
                throw $this->exception;
            }
        }

        protected function remediation(): string
        {
            return 'Confira TESTE_VARIAVEL.';
        }
    };

    return $diagnostic;
}

describe('check', function () {
    it('should pass when the probe completes', function () {
        $diagnostic = usersDiagnosticThatThrows(null);

        $result = $diagnostic->check();

        expect($result->status)->toBe(Status::Pass)
            ->and($result->summary)->toBe('Teste: a users-api aceitou a configuração do ambiente.')
            ->and($diagnostic->group)->toBe('users-api');
    });

    it('should fail with the remediation and safe details', function (Throwable $exception, string $details) {
        $diagnostic = usersDiagnosticThatThrows($exception);

        $result = $diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe($details)
            ->and($result->remediation)->toBe('Confira TESTE_VARIAVEL.')
            ->and($result->details)->not->toContain('sensitive');
    })->with([
        'connection' => [
            fn() => new ConnectionException('cURL error 28 https://sensitive.test'),
            'Sem resposta em 10 s ou conexão recusada.',
        ],
        'request' => [
            fn() => new RequestException(new Response(new Psr7Response(503, [], 'sensitive body'))),
            'HTTP 503.',
        ],
        'probe message' => [fn() => new RuntimeException('Configure TESTE.'), 'Configure TESTE.'],
    ]);

    it('should skip when the probe depends on a failing diagnostic', function () {
        $diagnostic = usersDiagnosticThatThrows(new DiagnosticSkipped('Depende de outro diagnóstico.'));

        $result = $diagnostic->check();

        expect($result->status)->toBe(Status::Skip)
            ->and($result->details)->toBe('Depende de outro diagnóstico.');
    });
});
