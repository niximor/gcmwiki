<?php

namespace lib;

interface Observable {
}

interface Observer {
    public function notify(Observable $observer);
}

class ObserverCollection {
    protected $observers;

    public function registerObserver(Observer $observer) {
        $this->observers[] = $observer;
    }

    public function notifyObservers(Observable $object) {
        foreach ($this->observers as $observer) {
            $observer->notify($object);
        }
    }
}

