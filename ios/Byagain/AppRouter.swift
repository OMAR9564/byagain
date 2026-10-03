import Foundation
import SwiftUI

struct SheetRequest: Identifiable {
	let id = UUID()
	let url: URL
}

@MainActor
final class AppRouter: ObservableObject {
	@Published var selection: AppTab = .today
	@Published var sheet: SheetRequest?

	let models: [AppTab: WebViewModel]
	private var sheetSubmitted = false

	init() {
		var built: [AppTab: WebViewModel] = [:]
		for tab in AppTab.allCases {
			built[tab] = WebViewModel(
				startURL: AppConfig.url(tab.path),
				presentation: .tab,
				router: nil
			)
		}
		models = built
		// Wire the router once self is fully initialized
		for model in built.values {
			model.router = self
		}
	}

	func model(for tab: AppTab) -> WebViewModel {
		// Every tab is created in init, so the lookup cannot miss
		models[tab]!
	}

	func select(_ tab: AppTab) {
		if tab == selection {
			model(for: tab).popToRoot()
		} else {
			selection = tab
		}
	}

	func open(_ url: URL) {
		let path = url.path

		// Check if path matches a tab's root path
		if let matchingTab = AppTab.allCases.first(where: { $0.path == path }) ?? (path == "/" ? .today : nil) {
			if matchingTab == selection {
				// Already on the right tab, pop to root
				model(for: matchingTab).popToRoot()
			} else {
				// Switch to the tab
				selection = matchingTab
				let model = self.model(for: matchingTab)
				if model.currentPath != path {
					model.load(url)
				}
			}
		} else {
			// Load in current tab
			model(for: selection).load(url)
		}
	}

	func presentSheet(_ url: URL) {
		sheetSubmitted = false
		sheet = SheetRequest(url: url)
	}

	func dismissSheet(reloadCurrent: Bool) {
		sheet = nil
		sheetSubmitted = reloadCurrent
	}

	func noteSheetSubmission() {
		sheetSubmitted = true
	}

	func sheetDidDismiss() {
		if sheetSubmitted {
			model(for: selection).reload()
		}
	}

	func didSignIn(from source: WebViewModel) {
		// Other tabs still sit on /login; send them to their own root
		for model in models.values where model !== source {
			model.load(model.startURL)
		}
	}
}
