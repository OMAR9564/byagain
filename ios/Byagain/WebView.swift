import SwiftUI
import WebKit

struct WebView: UIViewRepresentable {
	@ObservedObject var viewModel: WebViewModel

	func makeUIView(context: Context) -> WKWebView {
		viewModel.webView
	}

	func updateUIView(_ uiView: WKWebView, context: Context) {
		// No updates needed
	}
}
