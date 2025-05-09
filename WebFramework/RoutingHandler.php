<?php
/**
 * RoutingHandler.php
 * 
 * Handles automatic selection of registered RequestHandlers.
 * 
 * @author Patrick Matthias Garske <patrick@garske.link>
 * @since 0.1
 */

namespace WebFramework;

class RoutingHandler {

    /** @var array<RequestHandler> $handlers */
    private array $handlers;
    
    /**
     * Register a new RequestHandler with a specific URI
     *
     * @param  string $uri
     * @param  RequestHandler $handler
     * @return void
     */
    public function register(string $uri, RequestHandler $handler) {
        if(isset($this->handlers[$uri]))
            throw new \Exception("Handler already registered for this URI.");
        $this->handlers[$uri] = $handler;
    }
    
    /**
     * Automatically select the correct previously registered RequestHandler to process a request for a given URI and return the rendered HTML string
     *
     * @param  string $uri
     * @return string
     */
    public function handle(string $uri) {
        if(!isset($this->handlers[$uri])) {
            if(!isset($this->handlers["error"])) {
                return "404";
            }
            $this->handlers["error"]->handleGet(array("error" => "404"));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handlers[$uri]->handlePost($_POST);
        }
        return $this->handlers[$uri]->handleGet($_GET);

    }

}