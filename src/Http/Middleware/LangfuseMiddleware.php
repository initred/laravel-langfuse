<?php

declare(strict_types=1);

namespace Initred\Langfuse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Initred\Langfuse\Contracts\LangfuseClientInterface;
use Initred\Langfuse\Dto\TraceBody;
use Initred\Langfuse\Tracing\TraceUserId;
use Symfony\Component\HttpFoundation\Response;

class LangfuseMiddleware
{
    public function __construct(
        private readonly LangfuseClientInterface $langfuse,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->langfuse->isEnabled()) {
            return $next($request);
        }

        $trace = $this->langfuse->trace(new TraceBody(
            name: $request->route()?->getName() ?? $request->method().' '.$request->path(),
            userId: TraceUserId::from($request->user()),
            metadata: [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'source' => 'langfuse-middleware',
            ],
        ));

        $this->langfuse->setCurrentTrace($trace);

        /** @var Response $response */
        $response = $next($request);

        // The root observation is only exported once it ends. Ending it here
        // captures the handler; the terminate hook is the safety net for work
        // that happens after the response is sent.
        $trace->end(output: ['status' => $response->getStatusCode()]);

        return $response;
    }
}
