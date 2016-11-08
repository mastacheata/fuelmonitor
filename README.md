# fuelmonitor
Monitor MTS-K connected websites for fuel price changes and notify users on change in reference to a "home" station

The following sources for data are supported:
* CleverTankenMonitor - http://clever-tanken.de => HTML website crawler
* TankenTankenMonitor - http://tankentanken.de  => HTML website crawler 
* TankerKoenigMonitor - http://tankerkoenig.de  => Makes use of the Tankerkönig Spritpreis-API. The API is licensed under [Creative Commons CC-BY 4.0] (https://creativecommons.org/licenses/by/4.0/legalcode). You'll need an API-Key to use this service, which is available free of charge at http://creativecommons.tankerkoenig.de. Usage is subject to further conditions as noted on the website.
* VollerTankMonitor   - http://vollertank.de    => Publicly available, but undocumented JSON API used by the website to fetch data from its own backend

#Usage
example_preferences-\<xy\>.json are example configuration files with idMapping for stations to monitor and home station (id prefixed by underscore)

Notification of users happens via Pushover.net => Configure group or user keys in users.json (see example_users.json)

\<xy\>_getPrices.php is the trigger script for the corresponding service. Run this via cronjob every few minutes (MTS-K backend of the services updates every 5 minutes, so shorter intervals make no sense)

# Extend
If you'd like to add another service to monitor, just extend the FuelMonitor class and implement the fetchPrices method.
The method should return boolean value indicating success or failure and set the newPrices and comparePrices objects.
It should furthermore call the findCheapest method with the fuelName as sole parameter for each fueltype and assign the returned value to the minPrices[$fuelname] property.

#Logging
FuelMonitor base class initiates a MonoLog logger that catches all Exceptions and PHP errors. By default it is configured to log to a [Sentry](http://getsentry.com) instance via the Raven handler.
Feel free to add your own logging handler instead
