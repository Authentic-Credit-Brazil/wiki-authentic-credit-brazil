<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class CreateUserPageHooks {

	/**
	 * Implements UserLoginComplete hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/UserLoginComplete
	 * Check for existence of user page if $wgCreateUserPage_OnLogin is true
	 *
	 * @param User &$user the user object that was create on login
	 * @param string &$inject_html any HTML to inject after the login success message
	 */
	public static function onUserLoginComplete( User &$user, &$inject_html ) {
		if ( $GLOBALS["wgCreateUserPage_OnLogin"] ) {
			self::checkForUserPage( $user );
		}
	}

	/**
	 * Implements OutputPageParserOutput hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * Check for existence of user page if $wgCreateUserPage_OnLogin is false
	 *
	 * @param OutputPage &$out the OutputPage object to which wikitext is added
	 * @param ParserOutput $parseroutput a PaerserOutput object
	 */
	public static function onOutputPageParserOutput( OutputPage &$out,
		ParserOutput $parseroutput ) {
		$user = $out->getUser();
		if ( !$GLOBALS["wgCreateUserPage_OnLogin"] && !$user->isAnon() ) {
			self::checkForUserPage( $user );
		}
	}

	/**
	 * @param User $user
	 */
	private static function checkForUserPage( User $user ) {
		if ( $GLOBALS["wgCreateUserPage_AutoCreateUser"] ) {
			$username = $GLOBALS["wgCreateUserPage_AutoCreateUser"];
			wfDebugLog( 'CreateUserPage', 'AutoCreateUser: ' . $username );
			$autoCreateUser = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $username );
			if ( $autoCreateUser == false ) {
				wfDebugLog( 'CreateUserPage', 'AutoCreateUser invalid, using logged in user instead.' );
				$autoCreateUser = $user;
			}
		} else {
			$autoCreateUser = $user;
		}
		$title = Title::newFromText( 'User:' . $user->mName );
		if ( $title !== null && !$title->exists() ) {
			if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
				// MW 1.36+
				$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
			} else {
				$page = new WikiPage( $title );
			}
			$updater = $page->newPageUpdater( $autoCreateUser );
			$pageContent = new WikitextContent( $GLOBALS['wgCreateUserPage_PageContent'] );
			$updater->setContent( SlotRecord::MAIN, $pageContent );
			$edit_summary = CommentStoreComment::newUnsavedComment( 'create user page' );
			$updater->saveRevision( $edit_summary, EDIT_NEW );
		}
	}
}
