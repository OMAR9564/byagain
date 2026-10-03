import SwiftUI

struct RootView: View {
	@StateObject private var router = AppRouter()

	var body: some View {
		TabView(selection: Binding(
			get: { router.selection },
			set: {
				// Re-tapping the current tab pops it to root like every iOS app
				if $0 == router.selection {
					router.select($0)
				} else {
					router.selection = $0
				}
			}
		)) {
			ForEach(AppTab.allCases) { tab in
				TabWebScreen(model: router.model(for: tab))
					.tabItem { Label(tab.title, systemImage: tab.systemImage) }
					.tag(tab)
			}
		}
		.tint(Color("AccentColor"))
		.sheet(item: $router.sheet, onDismiss: { router.sheetDidDismiss() }) { request in
			SheetWebScreen(url: request.url, router: router)
				.presentationDragIndicator(.visible)
		}
	}
}
