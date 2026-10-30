<?php

use App\Deploy\Stacks\DetectionFailed;
use App\Deploy\Stacks\StackDetector;
use App\Enums\Stack;

require_once __DIR__.'/SourceHelpers.php';

function plan(string $path, int $port = 8080)
{
    return (new StackDetector)->plan($path, $port);
}

it('prefers the repository Dockerfile over everything else', function () {
    $plan = plan(sourceTree([
        'Dockerfile' => 'FROM scratch',
        'go.mod' => "module x\n\ngo 1.22\n",
        'package.json' => '{}',
    ]));

    expect($plan->stack)->toBe(Stack::Dockerfile)
        ->and($plan->dockerfile)->toBeNull();
});

it('refuses an unknown project or a missing directory', function () {
    expect(fn () => plan(sourceTree(['README.md' => 'hi'])))->toThrow(DetectionFailed::class, 'Could not detect')
        ->and(fn () => plan('/nonexistent/path'))->toThrow(DetectionFailed::class, 'does not exist');
});

describe('.NET', function () {
    it('builds a web project with the matching SDK and runtime', function () {
        $plan = plan(sourceTree(['src/Shop/Shop.csproj' => webCsproj('net9.0')]), 5000);

        expect($plan->stack)->toBe(Stack::Dotnet)
            ->and($plan->dockerfile)
            ->toContain('FROM mcr.microsoft.com/dotnet/sdk:9.0 AS build')
            ->toContain('FROM mcr.microsoft.com/dotnet/aspnet:9.0')
            ->toContain('dotnet publish "src/Shop/Shop.csproj"')
            ->toContain('ASPNETCORE_URLS=http://+:5000')
            ->toContain('EXPOSE 5000')
            ->toContain('USER $APP_UID')
            ->toContain('ENTRYPOINT ["dotnet", "Shop.dll"]');
    });

    it('honours AssemblyName and skips the non-root user before .NET 8', function () {
        $plan = plan(sourceTree(['Api.csproj' => webCsproj('net6.0', '<AssemblyName>Acme.Api</AssemblyName>')]));

        expect($plan->dockerfile)->toContain('ENTRYPOINT ["dotnet", "Acme.Api.dll"]')
            ->not->toContain('USER $APP_UID')
            ->and($plan->notes)->toContain('This .NET version has no non-root image user; the app runs as root inside its container.');
    });

    it('ignores class libraries and test projects next to the web project', function () {
        $plan = plan(sourceTree([
            'Web/Web.csproj' => webCsproj(),
            'Lib/Lib.csproj' => '<Project Sdk="Microsoft.NET.Sdk"><PropertyGroup><TargetFramework>net8.0</TargetFramework></PropertyGroup></Project>',
            'Web.Tests/Web.Tests.csproj' => '<Project Sdk="Microsoft.NET.Sdk"></Project>',
        ]));

        expect($plan->dockerfile)->toContain('"Web/Web.csproj"');
    });

    it('explains when there is no web project or several', function () {
        expect(fn () => plan(sourceTree(['Lib.csproj' => '<Project Sdk="Microsoft.NET.Sdk"/>'])))
            ->toThrow(DetectionFailed::class, 'none is a web project');

        expect(fn () => plan(sourceTree(['A/A.csproj' => webCsproj(), 'B/B.csproj' => webCsproj()])))
            ->toThrow(DetectionFailed::class, 'several .NET web projects');
    });

    it('rejects frameworks older than .NET 6 and broken project files', function () {
        expect(fn () => plan(sourceTree(['A.csproj' => webCsproj('netcoreapp3.1')])))
            ->toThrow(DetectionFailed::class, 'newer');

        expect(fn () => plan(sourceTree(['A.csproj' => '<not xml'])))
            ->toThrow(DetectionFailed::class, 'none is a web project');
    });

    it('refuses file names that could break out of the Dockerfile', function () {
        expect(fn () => plan(sourceTree(['a b/A.csproj' => webCsproj()])))
            ->toThrow(DetectionFailed::class, 'not supported');
    });
});

describe('Go', function () {
    it('builds the main package in the repository root', function () {
        $plan = plan(sourceTree([
            'go.mod' => "module acme/api\n\ngo 1.23.4\n",
            'main.go' => "package main\n\nfunc main() {}\n",
        ]), 9000);

        expect($plan->stack)->toBe(Stack::Go)
            ->and($plan->dockerfile)
            ->toContain('FROM golang:1.23 AS build')
            ->toContain('CGO_ENABLED=0 go build -trimpath -o /out/app .')
            ->toContain('distroless/static-debian12:nonroot')
            ->toContain('ENV PORT=9000');
    });

    it('finds a single program under cmd', function () {
        $plan = plan(sourceTree([
            'go.mod' => "module x\n\ngo 1.22\n",
            'cmd/server/main.go' => "package main\n",
            'internal/lib/lib.go' => "package lib\n",
        ]));

        expect($plan->dockerfile)->toContain('go build -trimpath -o /out/app ./cmd/server');
    });

    it('asks for a Dockerfile when the program is ambiguous or missing', function () {
        expect(fn () => plan(sourceTree([
            'go.mod' => "module x\n\ngo 1.22\n",
            'cmd/a/main.go' => "package main\n",
            'cmd/b/main.go' => "package main\n",
        ])))->toThrow(DetectionFailed::class, 'several Go programs');

        expect(fn () => plan(sourceTree(['go.mod' => "module x\n\ngo 1.22\n", 'lib.go' => "package lib\n"])))
            ->toThrow(DetectionFailed::class, 'No Go main package');

        expect(fn () => plan(sourceTree(['go.mod' => "module x\n"])))
            ->toThrow(DetectionFailed::class, 'no "go" version');
    });

    it('does not mistake a test file for the main package', function () {
        expect(fn () => plan(sourceTree([
            'go.mod' => "module x\n\ngo 1.22\n",
            'main_test.go' => "package main\n",
        ])))->toThrow(DetectionFailed::class, 'No Go main package');
    });
});

describe('Node.js', function () {
    it('uses npm ci, builds, prunes and runs npm start', function () {
        $plan = plan(sourceTree([
            'package.json' => json_encode(['scripts' => ['build' => 'tsc', 'start' => 'node dist/index.js'], 'engines' => ['node' => '>=20']]),
            'package-lock.json' => '{}',
        ]), 3000);

        expect($plan->stack)->toBe(Stack::Node)
            ->and($plan->dockerfile)
            ->toContain('FROM node:20-slim AS build')
            ->toContain('COPY package.json package-lock.json ./')
            ->toContain('RUN npm ci')
            ->toContain('RUN npm run build')
            ->toContain('RUN npm prune --omit=dev')
            ->toContain('ENV NODE_ENV=production PORT=3000')
            ->toContain('USER node')
            ->toContain('CMD ["npm","start"]');
    });

    it('falls back to npm install without a lockfile and skips the build step', function () {
        $plan = plan(sourceTree(['package.json' => json_encode(['scripts' => ['start' => 'node app.js']])]));

        expect($plan->dockerfile)->toContain('RUN npm install')
            ->not->toContain('npm ci')
            ->not->toContain('run build')
            ->toContain('FROM node:24-slim');
    });

    it('detects pnpm and yarn', function () {
        $pnpm = plan(sourceTree(['package.json' => json_encode(['scripts' => ['start' => 'x']]), 'pnpm-lock.yaml' => '']));
        expect($pnpm->dockerfile)->toContain('RUN corepack enable')
            ->toContain('RUN pnpm install --frozen-lockfile')
            ->toContain('RUN pnpm prune --prod')
            ->toContain('CMD ["pnpm","start"]');

        $yarn = plan(sourceTree(['package.json' => json_encode(['scripts' => ['start' => 'x', 'build' => 'y']]), 'yarn.lock' => '']));
        expect($yarn->dockerfile)->toContain('yarn install --frozen-lockfile')->toContain('RUN yarn build');

        $berry = plan(sourceTree(['package.json' => json_encode(['scripts' => ['start' => 'x']]), 'yarn.lock' => '', '.yarnrc.yml' => '']));
        expect($berry->dockerfile)->toContain('yarn install --immutable');
    });

    it('prefers the declared packageManager and reads .nvmrc', function () {
        $plan = plan(sourceTree([
            'package.json' => json_encode(['packageManager' => 'pnpm@9.1.0', 'scripts' => ['start' => 'x']]),
            'package-lock.json' => '{}',
            '.nvmrc' => "v22.11.0\n",
        ]));

        expect($plan->dockerfile)->toContain('pnpm install')->toContain('FROM node:22-slim');
    });

    it('runs the main file when there is no start script', function () {
        $plan = plan(sourceTree(['package.json' => json_encode(['main' => 'server.js'])]));

        expect($plan->dockerfile)->toContain('CMD ["node","server.js"]');
    });

    it('explains when there is nothing to run or the JSON is broken', function () {
        expect(fn () => plan(sourceTree(['package.json' => '{}'])))->toThrow(DetectionFailed::class, 'nothing to run')
            ->and(fn () => plan(sourceTree(['package.json' => '{nope'])))->toThrow(DetectionFailed::class, 'not valid JSON');
    });

    it('refuses a main file that could break out of the Dockerfile', function () {
        expect(fn () => plan(sourceTree(['package.json' => json_encode(['main' => 'a"; rm -rf /'])])))
            ->toThrow(DetectionFailed::class, 'not supported');
    });

    it('ignores unsupported engine versions', function () {
        $plan = plan(sourceTree(['package.json' => json_encode(['engines' => ['node' => '4.x'], 'scripts' => ['start' => 'x']])]));

        expect($plan->dockerfile)->toContain('FROM node:24-slim');
    });
});
