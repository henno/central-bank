Vaja arendada kooliülesandena pankade süsteemi (aine "Hajusrakendused"), milles oleks keskpank ja sellega seotud eraldiseisvad üksikud pangad. Õpetaja arendab keskpanga ja iga õpilane arendab ühe üksiku panga. Keskpank on nagu DNS server: ta võimaldab pankadel üksteist üles leida ja hoiab iga panga avalikku võtit, et pangad saaksid üksteist autentida. Esmalt vaja välja töötada detailne OpenAPI formaadis dokumentatsioon, mis võimldab implemneteerida nii keskkpanga kui suvalisi harupanku. Rakendust ennast ei ole praegu vaja välja töötada. 

Peab saama:

- panku registreerida keskpanka
- pankadesse registeerida kasutajaid
- lasta kasutajatel luua uusi kontosid endale
- valida valuutat loodavale kontole
- lasta kasutajatel teha ülekandeid samasse ja teistesse pankadesse (kui on erinev valuuta, siis valuutavahetus vastavalt hetkekursile)
- määrata ülekandes vastaspoole kontonumbri (esimesed 3 tähemärki peavad viitama sihtpangale, mis on leitav keskpangast, et teaks IP aadressi, kuhu pank peab päringu tegema, et saata ülekanne teise panka)
- teha ülekandeid teise panka ka siis, kui keskpank on maas
- teha ülekandeid ka siis, kui teine pank on ajutiselt maas (pending)
- teada, kui teine pank ei tulnudki timeouti jooksul onlinei ja tehing ebaõnnestus
- teada tehingu loomise ajal, kelle konto see on või kas see konto üldse eksisteerib (mitteautentitud päring GET /accounts/:number tagastab {"name": "xxx"} või 404)
- saada väljavõtte keskpangast registreeritud pankadest
- saata üksikutest pankadest heartbeat'i keskpangale (keskpank kustutab panga, kes pole poole tunni jooksul heartbeati saatnud)
- 


Mittefunktsionaalsed nõudmised:

- Konto numbrid on 8 tähemärgilised
- esimesed 3 tähemärki viitavad pangale
