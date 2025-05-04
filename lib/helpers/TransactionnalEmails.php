<?php

namespace Nano\helpers;

use DOMDocument;
use Nano\core\App;
use Nano\core\Env;
use Nano\core\Utils;
use PHPMailer\PHPMailer\PHPMailer;


class TransactionnalEmails
{
	// ------------------------------------------------------------------------- BRAND

	static protected string $__brandName;
	static protected string $__logoURL;
	static protected string $__logoLink;

	static function setBrand ( string $brandName, string $logoURL, string $logoLink ) {
		self::$__brandName = $brandName;
		self::$__logoURL = $logoURL;
		self::$__logoLink = $logoLink;
	}

	static protected string $__unsubscribeLink = "";

	static function setUnsubscribeLink ( string $unsubscribeLink ) {
		self::$__unsubscribeLink = $unsubscribeLink;
	}

	// ------------------------------------------------------------------------- BANNER

	static protected string $__banner;

	static function setBanner ( string $banner ) {
		self::$__banner = $banner;
	}

	// ------------------------------------------------------------------------- TEMPLATES

	static protected $__templateBase;

	static function setTemplateBase ( string $templateBase ) {
		self::$__templateBase = $templateBase;
	}

	// ------------------------------------------------------------------------- PHP MAILER INIT

	static protected PHPMailer $__mailer;

	static function init ( string $envPrefix = '' ) {
		if ( isset(static::$__mailer) )
			throw new \Exception("EmailService::init // Already initialized");

		$mailer = new PHPMailer(true);

		$mailer->isSMTP();
		$mailer->CharSet = PHPMailer::CHARSET_UTF8;
		$mailer->SMTPAutoTLS = false;
		$mailer->SMTPAuth = (
			!empty(Env::get($envPrefix.'MAIL_USERNAME'))
			&& !empty(Env::get($envPrefix.'MAIL_PASSWORD' ))
		);
		$mailer->SMTPSecure = Env::get($envPrefix.'MAIL_ENCRYPTION') ?? 'tls';
		$mailer->Host = Env::get($envPrefix.'MAIL_HOST');
		$mailer->Port = intval(Env::get($envPrefix.'MAIL_PORT') ?? "587");
		$mailer->Username = Env::get($envPrefix.'MAIL_USERNAME');
		$mailer->Password = Env::get($envPrefix.'MAIL_PASSWORD');
		//$senderEmail = Env::get($envPrefix.'MAIL_FROM_ADDRESS') ?? Env::get($envPrefix.'MAIL_USERNAME');
		//$mailer->Sender = $senderEmail;
		$mailer->setFrom(
			Env::get($envPrefix.'MAIL_FROM_ADDRESS') ?? Env::get($envPrefix.'MAIL_USERNAME'),
			Env::get($envPrefix.'MAIL_FROM_NAME') ?? Env::get($envPrefix.'MAIL_USERNAME')
		);
		$mailer->isHTML();

		self::$__mailer = $mailer;
	}

	// ------------------------------------------------------------------------- SEND EMAIL

	static function send ( string $to, string $template, array $vars, bool $debugRender = false ) {
		if ( !isset(static::$__mailer) || is_null(static::$__mailer) )
			throw new \Exception("EmailService::send // Not initialized");
		$mailer = static::$__mailer;
		// Load the template
		$emailTemplatePath = self::$__templateBase ?? '';
		$templateFile = $emailTemplatePath.$template.'.email.html';
		$templateContent = file_get_contents($templateFile);
		if ( $templateContent === false )
			throw new \Exception('EmailService::send // Unable to load the template file');
		// Split subject and inject in vars
		$parts = explode("\n---\n", $templateContent, 2);
		$subject = Utils::stache($parts[ 0 ], $vars);
		$templateContent = $parts[ 1 ];
		$vars = [ ...$vars, "subject" => $subject ];
		// Generate html layout
		$htmlContent = Utils::nl2br($templateContent);
		$layoutName = dirname($template);
		$layoutPath = $emailTemplatePath.$layoutName.'.layout.html';
		if ( file_exists($layoutPath) ) {
			$layoutTemplate = file_get_contents($layoutPath);
			$htmlContent = Utils::stache($layoutTemplate, [
				...$vars,
				"content" => $htmlContent
			]);
		}
		// Process the template with the variables for text email
		$textContent = preg_replace('/<a href="(.*?)">(.*?)<\/a>/', '$2: $1', $templateContent);
		$textContent = Utils::stache($textContent, [
			...$vars,
			"logo" => self::$__brandName
		]);
		$textContent = strip_tags($textContent);
		// Unsubscribe link
		if ( !empty(self::$__unsubscribeLink) ) {
			$mailer->addCustomHeader("List-Unsubscribe", self::$__unsubscribeLink);
			$vars = [ ...$vars, "unsubscribe" => self::$__unsubscribeLink ];
			$textContent .= "\n\n---\n\nUnsubscribe: ".self::$__unsubscribeLink;
		}
		// Inject banner
		if ( !empty(self::$__banner) ) {
			// As var in html
			$vars = [ ...$vars, "banner" => self::$__banner ];
			// Append in text
			$textContent = strip_tags(self::$__banner)."\n\n---\n\n".$textContent;
		} else {
			$vars = [ ...$vars, "banner" => "" ];
		}
		// Process the template with the variables for html email
		$htmlContent = Utils::stache($htmlContent, [
			...$vars,
			"logo" => (
			implode('', [
				'<a href="'.self::$__logoLink.'" class="logoLink">',
				empty(self::$__logoURL)
					? self::$__brandName
					: '<img class="logoImage" src="'.self::$__logoURL.'" alt="'.self::$__brandName.'" />',
				'</a>',
			])
			),
		]);
		$htmlContent = self::inlineStyles($htmlContent);
		// Output debug
		if ( $debugRender ) {
			return [
				"to" => $to,
				"subject" => $subject,
				"html" => $htmlContent,
				"text" => $textContent,
			];
		}
		// Prepare the email
		$mailer->addAddress($to);
		$mailer->Subject = '=?UTF-8?B?'.base64_encode($subject).'?=';
		$mailer->Body = $htmlContent;
		$mailer->AltBody = $textContent;
		// Send the email
		$emailSendDisable = Env::get('NANO_DISABLE_EMAIL_SEND', false);
		if ( $emailSendDisable === "debug" ) {
			App::stdout([
				"name" => "EmailService::send",
				"template" => $template,
				"to" => $to,
				"subject" => $subject,
				"vars" => $vars,
			]);
		} else if ( $emailSendDisable === false ) {
			$r = $mailer->send();
		}
		// Clear all recipients and attachments for next send
		$mailer->clearAddresses();
		$mailer->clearAttachments();
		return $r;
	}

	/**
	 * Parse HTML buffer, replace all class calls and inline styles.
	 * Uses DOMDocument PHP package.
	 *
	 * IMPORTANT rules :
	 * - Styles to map has to be in <head><style>
	 * - This style can only contain 1 level class declaration, no nesting.
	 * - Do not use double quotes, only single quotes, in declarations.
	 * - To be inlined, the class attribute has to contain only 1 class.
	 * - Media queries can be into a second <style /> after the first one
	 * - This first style tag will be removed after the inlining
	 *
	 * Ex :
	 * <html>
	 *   <head>
	 *     <style>
	 *       .willWork {
	 *         color: red;
	 *       }
	 *       .myLink {
	 *         color: green;
	 *       }
	 *       // do not do this !
	 *       .willNotWork a {
	 *         color: blue
	 *       }
	 *     </style>
	 *     <style>
	 *       // Media queries can go there
	 *
	 *     </style>
	 *   </head>
	 *   <body>
	 *     <div class="willWork>
	 *       <a class="myLink">Hello</a>
	 *     </div>
	 *   </body>
	 * </html>
	 *
	 * @param string $htmlContent
	 *
	 * @return false|string
	 */
	static function inlineStyles ( string $htmlContent ) {
		// Load document
		$doc = new \DOMDocument();
		@$doc->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$xpath = new \DOMXPath($doc);
		// Load first style
		$styleNode = $xpath->query('//style')->item(0);
		// No first style, return untouched HTML
		if ( !$styleNode )
			return $htmlContent;
		// Parse style rules
		$cssStyles = $styleNode->nodeValue;
		preg_match_all('/\.([\w-]+)\s*\{([^}]+)\}/', $cssStyles, $matches, PREG_SET_ORDER);
		$styles = [];
		foreach ( $matches as $match ) {
			$className = $match[ 1 ];
			$style = trim(preg_replace('/\s+/', ' ', $match[ 2 ]));
			$styles[ $className ] = $style;
		}
		// Browse all styles
		foreach ( $styles as $className => $style ) {
			// Find dom elements that has this class
			$elements = $xpath->query("//*[@class='$className']");
			foreach ( $elements as $element ) {
				// Inline style
				$existingStyle = $element->getAttribute('style');
				$existingStyle = !empty($existingStyle) ? $existingStyle.';' : '';
				$element->removeAttribute('class');
				$element->setAttribute('style', $existingStyle.trim($style));
			}
		}
		// Remove the first style tag
		$styleNode->parentNode->removeChild($styleNode);
		return $doc->saveHTML();
	}


	static function reportError ( string $to, string $scope, \Exception $error, mixed $other = null ) {
		$otherString = "";
		if ( !is_null($other) ) {
			try {
				$otherString = json_encode($other, JSON_NUMERIC_CHECK);
			} catch ( \Exception $error ) {
				$otherString = "Unable to decode";
			}
		}
		self::send($to, "error", [
			"scope" => $scope,
			"code" => $error->getCode(),
			"message" => $error->getMessage(),
			"file" => $error->getFile(),
			"line" => $error->getLine(),
			"trace" => $error->getTraceAsString(),
			"other" => $otherString,
		]);
	}
}
