import Foundation
import WebKit
import UIKit

@MainActor
final class WebViewModel: NSObject, ObservableObject, WKNavigationDelegate, WKUIDelegate, WKDownloadDelegate {
	@Published var loadFailed: Bool = false
	let webView: WKWebView
	private var downloadDestinations: [ObjectIdentifier: URL] = [:]

	override init() {
		let config = WKWebViewConfiguration()
		config.websiteDataStore = .default()
		config.limitsNavigationsToAppBoundDomains = true
		config.applicationNameForUserAgent = "byagainApp/1.0"
		config.allowsInlineMediaPlayback = true

		self.webView = WKWebView(frame: .zero, configuration: config)

		super.init()

		webView.allowsBackForwardNavigationGestures = true
		webView.isOpaque = false
		webView.backgroundColor = .clear
		webView.scrollView.backgroundColor = .clear
		webView.scrollView.contentInsetAdjustmentBehavior = .never
		webView.navigationDelegate = self
		webView.uiDelegate = self

		let refreshControl = UIRefreshControl()
		refreshControl.addTarget(self, action: #selector(refreshWebView(_:)), for: .valueChanged)
		webView.scrollView.refreshControl = refreshControl

		webView.load(URLRequest(url: AppConfig.startURL))
	}

	@objc private func refreshWebView(_ sender: UIRefreshControl) {
		reload()
	}

	func reload() {
		loadFailed = false
		if webView.url == nil {
			webView.load(URLRequest(url: AppConfig.startURL))
		} else {
			webView.reload()
		}
	}

	// MARK: - WKNavigationDelegate

	func webView(
		_ webView: WKWebView,
		decidePolicyFor navigationAction: WKNavigationAction,
		decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
	) {
		guard let url = navigationAction.request.url else {
			decisionHandler(.allow)
			return
		}

		let isHttpOrHttps = url.scheme == "http" || url.scheme == "https"
		let isAppBoundHost = url.host == AppConfig.host
		let isAboutBlank = url.absoluteString == "about:blank"

		if isHttpOrHttps && isAppBoundHost {
			decisionHandler(.allow)
		} else if isAboutBlank {
			decisionHandler(.allow)
		} else if url.scheme == "mailto" || url.scheme == "tel" || (isHttpOrHttps && !isAppBoundHost) {
			decisionHandler(.cancel)
			UIApplication.shared.open(url)
		} else {
			decisionHandler(.cancel)
		}
	}

	func webView(
		_ webView: WKWebView,
		decidePolicyFor navigationResponse: WKNavigationResponse,
		decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void
	) {
		guard let response = navigationResponse.response as? HTTPURLResponse else {
			decisionHandler(.allow)
			return
		}

		let contentDisposition = response.value(forHTTPHeaderField: "Content-Disposition") ?? ""
		let isAttachment = contentDisposition.lowercased().hasPrefix("attachment")
		let canShowMimeType = navigationResponse.canShowMIMEType

		if isAttachment || !canShowMimeType {
			decisionHandler(.download)
		} else {
			decisionHandler(.allow)
		}
	}

	func webView(
		_ webView: WKWebView,
		navigationAction: WKNavigationAction,
		didBecome download: WKDownload
	) {
		download.delegate = self
	}

	func webView(
		_ webView: WKWebView,
		navigationResponse: WKNavigationResponse,
		didBecome download: WKDownload
	) {
		download.delegate = self
	}

	func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
		loadFailed = false
		webView.scrollView.refreshControl?.endRefreshing()
	}

	func webView(
		_ webView: WKWebView,
		didFailProvisionalNavigation navigation: WKNavigation!,
		withError error: any Error
	) {
		handleNavigationError(error)
	}

	func webView(
		_ webView: WKWebView,
		didFail navigation: WKNavigation!,
		withError error: any Error
	) {
		handleNavigationError(error)
	}

	private func handleNavigationError(_ error: any Error) {
		webView.scrollView.refreshControl?.endRefreshing()

		let nsError = error as NSError
		if nsError.code == NSURLErrorCancelled {
			return
		}

		if nsError.domain == "WebKitErrorDomain" && nsError.code == 102 {
			return
		}

		if webView.url == nil || webView.backForwardList.currentItem == nil {
			loadFailed = true
		}
	}

	func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
		webView.reload()
	}

	// MARK: - WKUIDelegate

	func webView(
		_ webView: WKWebView,
		createWebViewWith configuration: WKWebViewConfiguration,
		for navigationAction: WKNavigationAction,
		windowFeatures: WKWindowFeatures?
	) -> WKWebView? {
		guard let url = navigationAction.request.url else {
			return nil
		}

		let isAppBoundHost = url.host == AppConfig.host

		if !isAppBoundHost {
			UIApplication.shared.open(url)
			return nil
		}

		webView.load(navigationAction.request)
		return nil
	}

	func webView(
		_ webView: WKWebView,
		runJavaScriptAlertPanelWithMessage message: String,
		initiatedByFrame frame: WKFrameInfo,
		completionHandler: @escaping () -> Void
	) {
		guard let presenter = topViewController else {
			completionHandler()
			return
		}
		let alert = UIAlertController(title: nil, message: message, preferredStyle: .alert)
		alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in
			completionHandler()
		})
		presenter.present(alert, animated: true)
	}

	func webView(
		_ webView: WKWebView,
		runJavaScriptConfirmPanelWithMessage message: String,
		initiatedByFrame frame: WKFrameInfo,
		completionHandler: @escaping (Bool) -> Void
	) {
		guard let presenter = topViewController else {
			completionHandler(false)
			return
		}
		let alert = UIAlertController(title: nil, message: message, preferredStyle: .alert)
		alert.addAction(UIAlertAction(title: "Cancel", style: .cancel) { _ in
			completionHandler(false)
		})
		alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in
			completionHandler(true)
		})
		presenter.present(alert, animated: true)
	}

	func webView(
		_ webView: WKWebView,
		runJavaScriptTextInputPanelWithPrompt prompt: String,
		defaultText: String?,
		initiatedByFrame frame: WKFrameInfo,
		completionHandler: @escaping (String?) -> Void
	) {
		guard let presenter = topViewController else {
			completionHandler(nil)
			return
		}
		let alert = UIAlertController(title: nil, message: prompt, preferredStyle: .alert)
		alert.addTextField { $0.text = defaultText }
		alert.addAction(UIAlertAction(title: "Cancel", style: .cancel) { _ in
			completionHandler(nil)
		})
		alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in
			completionHandler(alert.textFields?.first?.text)
		})
		presenter.present(alert, animated: true)
	}

	// MARK: - WKDownloadDelegate

	func download(
		_ download: WKDownload,
		decideDestinationUsing response: URLResponse,
		suggestedFilename: String,
		completionHandler: @escaping (URL?) -> Void
	) {
		let dir = FileManager.default.temporaryDirectory
			.appendingPathComponent(UUID().uuidString, isDirectory: true)
		do {
			try FileManager.default.createDirectory(at: dir, withIntermediateDirectories: true)
		} catch {
			completionHandler(nil)
			return
		}

		let fileURL = dir.appendingPathComponent(suggestedFilename)
		downloadDestinations[ObjectIdentifier(download)] = fileURL
		completionHandler(fileURL)
	}

	func downloadDidFinish(_ download: WKDownload) {
		guard let fileURL = downloadDestinations.removeValue(forKey: ObjectIdentifier(download)) else {
			return
		}

		let controller = UIActivityViewController(activityItems: [fileURL], applicationActivities: nil)
		if let popover = controller.popoverPresentationController {
			popover.sourceView = webView
			popover.sourceRect = CGRect(x: webView.bounds.midX, y: webView.bounds.midY, width: 0, height: 0)
		}
		topViewController?.present(controller, animated: true)
	}

	func download(_ download: WKDownload, didFailWithError error: any Error, resumeData: Data?) {
		downloadDestinations.removeValue(forKey: ObjectIdentifier(download))
	}

	// MARK: - Helpers

	private var topViewController: UIViewController? {
		let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
		guard let scene = scenes.first(where: { $0.activationState == .foregroundActive }) ?? scenes.first,
			  let window = scene.keyWindow ?? scene.windows.first else {
			return nil
		}

		var vc = window.rootViewController
		while let presented = vc?.presentedViewController {
			vc = presented
		}
		return vc
	}
}
