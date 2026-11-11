<?php

use Illuminate\Container\Container;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Diagnostics\ApplicationKeyIsSet;
use Laravel\Doctor\Doctor;
use Laravel\Doctor\DoctorServiceProvider;
use MobileStock\Gatekeeper\Providers\GatekeeperServiceProvider;

describe('registerDoctorDiagnostics', function () {
    it('should add the users-api diagnostics to the doctor of an app that installs laravel/doctor', function () {
        $this->app->register(DoctorServiceProvider::class);

        $registered = $this->app->make(Doctor::class)->registered();

        expect($registered)->toContain(ApplicationKeyIsSet::class, ...GatekeeperServiceProvider::DOCTOR_DIAGNOSTICS);

        foreach (GatekeeperServiceProvider::DOCTOR_DIAGNOSTICS as $class) {
            expect($this->app->make($class))->toBeInstanceOf(Diagnostic::class);
        }
    });

    it('should leave the doctor untouched when laravel/doctor is not installed', function () {
        $container = new Container();
        $doctor = new Doctor($container);
        $container->instance(Doctor::class, $doctor);
        $provider = new class ($container) extends GatekeeperServiceProvider {
            protected function doctorIsInstalled(): bool
            {
                return false;
            }
        };

        invokeProtectedMethod($provider, 'registerDoctorDiagnostics');
        $registered = $container->make(Doctor::class)->registered();

        expect($registered)->toBe([]);
    });
});
