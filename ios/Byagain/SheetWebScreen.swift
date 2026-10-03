import SwiftUI

struct SheetWebScreen: View {
	@StateObject private var model: WebViewModel

	init(url: URL, router: AppRouter) {
		_model = StateObject(wrappedValue: WebViewModel(
			startURL: url,
			presentation: .sheet,
			router: router
		))
	}

	var body: some View {
		ZStack {
			Color("Canvas")
				.ignoresSafeArea()

			WebView(viewModel: model)
				.ignoresSafeArea(edges: .bottom)

			if model.loadFailed {
				VStack(spacing: 16) {
					Text("You're offline")
						.font(.headline)
					Text("byagain couldn't reach the server.")
						.font(.body)
						.foregroundColor(.secondary)
					Button(action: {
						model.reload()
					}) {
						Text("Try again")
							.fontWeight(.semibold)
							.frame(maxWidth: .infinity)
							.padding()
					}
					.buttonStyle(.borderedProminent)
					.controlSize(.large)
					.padding()
				}
				.padding()
				.background(.regularMaterial)
				.cornerRadius(16)
				.padding()
			}
		}
	}
}
