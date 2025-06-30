<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DirectoryService;

class DirectoryController
{
    private DirectoryService $directoryService;

    public function __construct()
    {
        $this->directoryService = new DirectoryService();
    }

    public function add(Request $request): Response
    {
        return $this->directoryService->add($request);
    }

    public function rename(Request $request): Response
    {
        return $this->directoryService->rename($request);
    }

    public function get(Request $request): Response
    {
        return $this->directoryService->get($request);
    }

    public function move(Request $request): Response
    {
        return $this->directoryService->move($request);
    }

    public function delete(Request $request): Response
    {
        return $this->directoryService->delete($request);
    }

    public function share(Request $request): Response
    {
        return $this->directoryService->share($request);
    }

    public function unshare(Request $request): Response
    {
        return $this->directoryService->unshare($request);
    }

    public function download(Request $request): Response
    {
        return $this->directoryService->download($request);
    }
}
