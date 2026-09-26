<?php
/**
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 */

namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Migration\ISimpleMigration;
use OCP\Migration\IOutput;

/**
 * This adds the default client ids for ownCloud mobile and desktop clients
 */
class Version20170329194544 implements ISimpleMigration {
	private static $registry = [
		['Desktop Client', 'http://localhost:*', 'xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69', 'UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh'],
		['Android', 'oc://android.owncloud.com', 'e4rAsNUSIUs0lF4nbv9FmCeUkTlV9GdgTLDH1b5uie7syb90SzEVrbN7HIpmWJeD', 'dInFYGV33xKzhbRmpqQltYNdfLdJIfJ9L5ISoKhNoT9qZftpdWSP71VrpGR9pmoD'],
		['iOS', 'oc://ios.owncloud.com', 'mxd5OQDk6es5LzOzRvidJNfXLUZS2oN3oUFeXPP8LpPrhx3UroJFduGEYIBOxkY1', 'KFeFWWEZO9TkisIQzR3fo7hfiMXlOpaqP8CFuTbSHzV1TUuGECglPxpiVKJfOXIx']
	];
	/**
	 * @param IOutput $out
	 */
	public function run(IOutput $out) {
		// this is necessary to make the app work with OC <10.0.3
		\call_user_func(['OC_App', 'loadApp'], 'oauth2', false);
		foreach (self::$registry as list($name, $redirectUrl, $clientId, $secret)) {
			// Eine aus oauth2 0.1.0 übernommene Datenbank hat keinen eindeutigen
			// Index auf dem Namen, und auf der Kennung gab es nie einen. Wer den
			// Desktop- oder Mobil-Client damals von Hand eingetragen hat, bekäme
			// sonst einen zweiten Eintrag mit derselben Kennung - und
			// findByIdentifier() scheitert dann an MultipleObjectsReturned, die
			// Anmeldung dieses Clients also komplett. Vorhandene Einträge bleiben,
			// wie sie sind.
			if ($this->isKnownIdentifier($clientId)) {
				$out->info("The client <$name> already known.");
				continue;
			}
			// Ist nur der Name belegt, fehlt die offizielle Kennung - die App
			// kann sich dann nicht per OAuth2 anmelden. Überschrieben wird der
			// vorhandene Eintrag trotzdem nicht; die Verwaltung muss das sehen.
			$nameHolder = $this->getIdentifierOfClientNamed($name);
			if ($nameHolder !== null) {
				$message = "The client <$name> was not added: the name is already used by the client with id <$nameHolder>."
					. " The official $name app cannot sign in via OAuth2 until it is added - for example, rename the"
					. " existing client with 'occ oauth2:modify-client \"$name\" name \"<new name>\"' and run"
					. " 'occ oauth2:add-client \"$name\" $clientId $secret \"$redirectUrl\"'.";
				$out->warning($message);
				\OC::$server->getLogger()->warning($message, ['app' => 'oauth2']);
				continue;
			}
			try {
				$this->addClient($name, $redirectUrl, $clientId, $secret);

				$out->info("The client <$name> has been added.");
			} catch (UniqueConstraintViolationException $ex) {
				$out->info("The client <$name> already known.");
			}
		}
	}

	/**
	 * Ob es schon einen Client mit dieser Kennung gibt.
	 *
	 * @param string $clientId
	 * @return bool
	 */
	protected function isKnownIdentifier($clientId) {
		/** @var ClientMapper $mapper */
		$mapper = \OC::$server->query(ClientMapper::class);
		try {
			$mapper->findByIdentifier($clientId);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		} catch (MultipleObjectsReturnedException $e) {
			return true;
		}
	}

	/**
	 * Die Kennung des Clients, der diesen Namen trägt, oder null, wenn der Name
	 * frei ist. Tragen ihn mehrere (0.1.0 hatte keinen eindeutigen Index), sind
	 * ihre Kennungen mit Komma verbunden.
	 *
	 * @param string $name
	 * @return string|null
	 */
	protected function getIdentifierOfClientNamed($name) {
		/** @var ClientMapper $mapper */
		$mapper = \OC::$server->query(ClientMapper::class);
		try {
			return $mapper->findByName($name)->getIdentifier();
		} catch (DoesNotExistException $e) {
			return null;
		} catch (MultipleObjectsReturnedException $e) {
			$identifiers = [];
			foreach ($mapper->findAll() as $client) {
				// die Datenbank vergleicht je nach Kollation ohne Groß-/Kleinschreibung
				if (\strcasecmp($client->getName(), $name) === 0) {
					$identifiers[] = $client->getIdentifier();
				}
			}
			return $identifiers === [] ? '(several)' : \implode(', ', $identifiers);
		}
	}

	/**
	 * @param string $name
	 * @param string $redirectUrl
	 * @param string $clientId
	 * @param string $secret
	 */
	protected function addClient($name, $redirectUrl, $clientId, $secret) {
		/** @var ClientMapper $mapper */
		$mapper = \OC::$server->query(ClientMapper::class);

		$client = new Client();
		$client->setIdentifier($clientId);
		$client->setSecret($secret);
		$client->setRedirectUri($redirectUrl);
		$client->setName($name);
		$client->setAllowSubdomains(false);

		$mapper->insert($client);
	}
}
