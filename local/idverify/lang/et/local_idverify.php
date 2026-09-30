<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Estonian strings for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['birthdate'] = 'Sünniaeg';
$string['cachedef_eeid'] = 'eeID OpenID seadistus ja allkirjavõtmed';
$string['check:hmackey_error'] = 'Isikutuvastuse HMAC-võti puudub või on liiga lühike';
$string['check:hmackey_ok'] = 'Isikutuvastuse HMAC-võti on seadistatud';
$string['checkhmackey'] = 'Isikutuvastuse võti';
$string['error:alreadyverified'] = 'See konto on juba tuvastatud teise isikuga. Palun võta ühendust kasutajatoega.';
$string['error:cancelled'] = 'Tuvastamine katkestati.';
$string['error:conflict'] = 'See isik on juba seotud teise kontoga. Palun võta ühendust kasutajatoega.';
$string['error:countrynotallowed'] = 'Selle riigi isikuid siin tuvastada ei saa. Palun võta ühendust kasutajatoega.';
$string['error:hmackey'] = 'Isikutuvastus ei ole sellel saidil seadistatud.';
$string['error:hmackey_admin'] = 'Isikutuvastus on välja lülitatud: <code>$CFG->local_idverify_hmackey</code> puudub failist config.php või on lühem kui 32 baiti. Vaata plugina README-faili.';
$string['error:invaliddata'] = 'Isikuandmed on puudulikud või vigased.';
$string['error:invalididcode'] = 'Isikukood ei ole korrektne.';
$string['error:methodnotallowed'] = 'Seda sisselogimisviisi ei saa isikutuvastuseks kasutada. Palun kasuta Eesti ID-kaarti, Mobiil-ID-d või Smart-ID-d.';
$string['error:noidentity'] = 'Sellel kasutajal pole tuvastatud isikut.';
$string['error:notavailable'] = 'Isikutuvastus ei ole praegu saadaval. Palun proovi hiljem uuesti või võta ühendust kasutajatoega.';
$string['error:provider'] = 'Tuvastamine ebaõnnestus. Palun proovi uuesti. Kui see ei õnnestu, võta ühendust kasutajatoega.';
$string['error:state'] = 'Tuvastamise seanss ei kehti. Palun alusta uuesti.';
$string['error:stateexpired'] = 'Tuvastamine võttis liiga kaua aega. Palun alusta uuesti.';
$string['event:idcode_decrypted'] = 'Tuvastatud isikukood trükiti tunnistusele';
$string['event:idcode_viewed'] = 'Isikukoodi täielik vaatamine';
$string['event:identity_retained'] = 'Isikuandmed säilitati (väljastatud tunnistused)';
$string['event:identity_revoked'] = 'Isikutuvastus tühistatud';
$string['event:identity_verified'] = 'Isik tuvastatud';
$string['event:verification_conflict'] = 'Isik on juba seotud teise kontoga';
$string['event:verification_failed'] = 'Isikutuvastus ebaõnnestus';
$string['idcode'] = 'Isikukood';
$string['idverify:manage'] = 'Tuvastatud isikute haldamine';
$string['idverify:verifyself'] = 'Enda isiku tuvastamine';
$string['idverify:viewidcode'] = 'Isikukoodide täielik vaatamine';
$string['intro'] = 'Kursuse tunnistusel peavad olema sinu ametlik nimi ja isikukood. Tuvasta oma isik ühe korra Smart-ID, ID-kaardi või Mobiil-ID-ga. See võtab umbes 20 sekundit.';
$string['issuedcount'] = 'Väljastatud tunnistusi';
$string['legalname'] = 'Ametlik nimi';
$string['manage'] = 'Tuvastatud isikud';
$string['manage:empty'] = 'Tuvastatud isikuid ei leitud.';
$string['manage:search'] = 'Nimi või e-post';
$string['manual:alreadyverified'] = 'See kasutaja on juba tuvastatud. Tühista kõigepealt praegune tuvastus.';
$string['manual:codeorbirthdate'] = 'Sisesta isikukood või, kui isikul seda pole, sünniaeg.';
$string['manual:conflict'] = 'See isik on juba seotud teise kontoga. Midagi ei salvestatud.';
$string['manual:done'] = 'Isik tuvastati käsitsi.';
$string['manual:firstname'] = 'Ametlik eesnimi (eesnimed)';
$string['manual:idcode'] = 'Isikukood';
$string['manual:idcode_help'] = 'Nagu isikut tõendaval dokumendil. Eesti isikukood kontrollitakse. Kui isikul isikukoodi pole, jäta väli tühjaks ja sisesta sünniaeg.';
$string['manual:intro'] = 'Kasuta seda ainult siis, kui õppija ei saa tuvastada Smart-ID, ID-kaardi ega Mobiil-ID-ga. Kontrolli isikut tõendavat dokumenti ise ja kirjelda märkuses, kuidas.';
$string['manual:lastname'] = 'Ametlik perekonnanimi';
$string['manual:note'] = 'Märkus';
$string['manual:note_help'] = 'Kuidas isikut kontrolliti, nt „pass kontrollitud kohapeal 27.09.2026“. Kohustuslik.';
$string['manual:submit'] = 'Tuvasta';
$string['manual:title'] = 'Tuvasta käsitsi';
$string['method'] = 'Viis';
$string['method:idcard'] = 'ID-kaart';
$string['method:manual'] = 'Tuvastanud administraator';
$string['method:mobileid'] = 'Mobiil-ID';
$string['method:smartid'] = 'Smart-ID';
$string['mock:approve'] = 'Kinnita selle isikuna';
$string['mock:title'] = 'Näidis-isikutuvastus';
$string['mock:warning'] = 'Ainult arenduseks: päris isikutuvastust ei toimu. See leht on saadaval ainult arendaja silumisrežiimis.';
$string['myidentity'] = 'Minu isikutuvastus';
$string['nameslocked'] = 'Sinu ees- ja perekonnanimi on sellel saidil sinu ametlik nimi ja neid ei saa muuta.';
$string['pluginname'] = 'Isikutuvastus';
$string['privacy:export:undecryptable'] = '(salvestatud krüpteeritult; ei saa dekrüpteerida, sest saidi krüpteerimisvõti on muutunud)';
$string['privacy:metadata:core_user'] = 'Tuvastamisel märgib plugin kohandatud profiilivälja „Isik tuvastatud“ ja võib asendada ees- ja perekonnanime ametliku nimega.';
$string['privacy:metadata:eeid'] = 'Õppija logib ID-kaardi, Mobiil-ID või Smart-ID-ga sisse Eesti Interneti SA isikutuvastusteenuses eeID (eeid.ee). Moodle saadab eeID-le ainult oma kliendi ID ja juhuslikud turvaväärtused ning saab tuvastatud isikuandmed vastu allkirjastatud tõendis. eeID säilitab oma logisid (aeg, IP-aadress, viis, vead) kolm kuud.';
$string['privacy:metadata:eeid:birthdate'] = 'Sünniaeg, saadud eeID kaudu.';
$string['privacy:metadata:eeid:firstname'] = 'Ametlik eesnimi, saadud eeID kaudu.';
$string['privacy:metadata:eeid:idcode'] = 'Isikukood, saadud eeID kaudu.';
$string['privacy:metadata:eeid:lastname'] = 'Ametlik perekonnanimi, saadud eeID kaudu.';
$string['privacy:metadata:eeid:method'] = 'Kasutatud sisselogimisviis (ID-kaart, Mobiil-ID või Smart-ID), saadud eeID kaudu.';
$string['privacy:metadata:identity'] = 'Kasutaja tuvastatud ametlikud isikuandmed. Õiguslik alus: juriidiline kohustus (GDPR art 6 lg 1 p c): täiskasvanute koolituse seaduse ja täienduskoolituse standardi (haridus- ja teadusministri määrus, RT I, 22.04.2025, 2) järgi peavad täienduskoolituse tunnistusel ja tõendil olema õppija nimi ja isikukood, selle puudumisel sünniaeg. Kui andmed on trükitud väljastatud tunnistustele, siis kustutamistaotluse korral need säilitatakse ja andmekaitsespetsialist otsustab käsitsi.';
$string['privacy:metadata:identity:birthdate'] = 'Sünniaeg.';
$string['privacy:metadata:identity:country'] = 'Isikukoodi riik.';
$string['privacy:metadata:identity:firstname'] = 'Ametlik eesnimi (eesnimed).';
$string['privacy:metadata:identity:idcode_enc'] = 'Isikukood, krüpteeritult.';
$string['privacy:metadata:identity:idcode_hmac'] = 'Isikukoodi võtmega räsi, mida kasutatakse ainult selleks, et üht isikut ei seotaks kahe kontoga.';
$string['privacy:metadata:identity:lastname'] = 'Ametlik perekonnanimi.';
$string['privacy:metadata:identity:method'] = 'Tuvastamise viis (Smart-ID, ID-kaart, Mobiil-ID või administraator).';
$string['privacy:metadata:identity:note'] = 'Administraatori märkus käsitsi tuvastamise kohta.';
$string['privacy:metadata:identity:provider'] = 'Teenus, millega isik tuvastati.';
$string['privacy:metadata:identity:timeverified'] = 'Tuvastamise aeg.';
$string['privacy:metadata:identity:userid'] = 'Kasutaja, kellele isikuandmed kuuluvad.';
$string['privacy:metadata:identity:verifiedby'] = 'Administraator, kes isiku käsitsi tuvastas.';
$string['privacy:metadata:issued'] = 'Väljastatud tunnistused, millele tuvastatud isikuandmed trükiti. Selle järgi otsustatakse, kas isikuandmed tuleb säilitada.';
$string['privacy:metadata:issued:code'] = 'Tunnistuse kontrollkood.';
$string['privacy:metadata:issued:customcertid'] = 'Tunnistuse tegevus.';
$string['privacy:metadata:issued:timeissued'] = 'Tunnistuse väljastamise aeg.';
$string['privacy:metadata:issued:userid'] = 'Kasutaja, kellele tunnistus väljastati.';
$string['privacynote'] = 'Sinu isikukood salvestatakse krüpteeritult ja seda näidatakse ainult sinu tunnistustel.';
$string['profile:details'] = 'Üksikasjad';
$string['profile:notverified'] = 'Isik on tuvastamata';
$string['profile:title'] = 'Isikutuvastus';
$string['profile:verified'] = 'Tuvastatud {$a->date} ({$a->method})';
$string['profile:verifiedmanual'] = 'Tuvastanud administraator {$a}';
$string['profilecategory'] = 'Isikutuvastus';
$string['profilefield'] = 'Isik tuvastatud';
$string['profilefield_desc'] = 'Seda välja haldab isikutuvastuse plugin. Kasuta seda ligipääsupiirangutes (Kasutaja profiil: Isik tuvastatud on võrdne 1). Ära muuda käsitsi.';
$string['provider'] = 'Teenus';
$string['provider:eeid'] = 'eeID (ID-kaart, Mobiil-ID, Smart-ID)';
$string['provider:mock'] = 'Näidis (ainult arenduseks)';
$string['provider:none'] = 'Välja lülitatud';
$string['revoke'] = 'Tühista';
$string['revoke:confirm'] = 'Kas tühistada kasutaja {$a} isikutuvastus? Salvestatud nimi ja isikukood kustutatakse, „Isik tuvastatud“ lipp eemaldatakse ja kasutaja logitakse välja.';
$string['revoke:done'] = 'Kasutaja {$a} isikutuvastus tühistati.';
$string['revoke:issuedwarning'] = 'Selle isiku andmed on juba trükitud {$a} tunnistusele. customcert koostab tunnistuse igal vaatamisel uuesti, seega pärast tühistamist pole neil isikukoodi ega ametlikku nime.';
$string['setting:allowedcountries'] = 'Lubatud riigid';
$string['setting:allowedcountries_desc'] = 'Komaga eraldatud riigikoodid (ISO), mille isikuid tohib tuvastada, nt EE. Ei kehti käsitsi tuvastamisele.';
$string['setting:eeid_clientid'] = 'Kliendi ID (client ID)';
$string['setting:eeid_env'] = 'Keskkond';
$string['setting:eeid_env_desc'] = 'Test kasutab aadressi test-auth.eeid.ee, kus töötavad ainult testkasutajad (tasuta). Toodang kasutab aadressi auth.eeid.ee (tasu iga tuvastuse eest). Kliendi ID kuulub ühte keskkonda.';
$string['setting:eeid_env_production'] = 'Toodang (auth.eeid.ee)';
$string['setting:eeid_env_test'] = 'Test (test-auth.eeid.ee)';
$string['setting:eeid_secret'] = 'Kliendi saladus (secret)';
$string['setting:eeid_secret_desc'] = 'Hoia saladuses. Sellega saab igaüks sinu kulul sinu eeID teenust kasutada.';
$string['setting:eeidheading'] = 'eeID';
$string['setting:eeidheading_desc'] = 'Loo teenus aadressil eeid.ee (Services, Create new service): tüüp Authentication, allpool valitud keskkond, tagasisuunamise aadress <code>{$a}</code>, autentimise ulatus ainult openid ning Eesti ID-kaart, Mobiil-ID ja Smart-ID. Kui Eesti Interneti SA teenuse kinnitab, sisesta siia selle kliendi ID ja saladus.';
$string['setting:introtext'] = 'Tutvustustekst lehel „Minu isikutuvastus“';
$string['setting:introtext_desc'] = 'Näidatakse nupu „Tuvasta kohe“ kohal. Jäta tühjaks vaiketeksti jaoks: „{$a}“. Mitme keele jaoks kasuta mitmekeelse sisu filtrit, nt <code>&lt;span lang="et" class="multilang"&gt;…&lt;/span&gt;&lt;span lang="en" class="multilang"&gt;…&lt;/span&gt;</code>.';
$string['setting:mock_birthdate'] = 'Näidis-sünniaeg (AAAA-KK-PP)';
$string['setting:mock_country'] = 'Näidis-riik';
$string['setting:mock_firstname'] = 'Näidis-eesnimi';
$string['setting:mock_idcode'] = 'Näidis-isikukood';
$string['setting:mock_lastname'] = 'Näidis-perekonnanimi';
$string['setting:mockheading'] = 'Näidis-isikutuvastus';
$string['setting:mockheading_desc'] = 'Võltsisik, kelle näidis-isikutuvastus tagastab. Näidatakse ainult arendaja silumisrežiimis.';
$string['setting:overwritenames'] = 'Kasuta ametlikku nime';
$string['setting:overwritenames_desc'] = 'Pärast tuvastamist asendatakse kasutaja ees- ja perekonnanimi tuvastatud ametliku nimega ning neid ei saa muuta.';
$string['setting:provider'] = 'Isikutuvastuse teenus';
$string['setting:provider_desc'] = 'Teenus, millega isikuid tuvastatakse.';
$string['status:verified'] = 'Sinu isik on tuvastatud.';
$string['timeverified'] = 'Tuvastatud';
$string['verified'] = 'Sinu isik on tuvastatud.';
$string['verifiedby'] = 'Tuvastaja';
$string['verifyidentity'] = 'Tuvasta isik';
$string['verifynow'] = 'Tuvasta kohe';
$string['view:reveal'] = 'Näita isikukoodi';
$string['view:revealnote'] = 'Täieliku isikukoodi näitamine logitakse.';
