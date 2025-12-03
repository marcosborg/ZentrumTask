<!-- Contact Section -->
    <section class="container pb-5" id="contactos">
      <div class="contact-section">
        <h3 class="mb-4">Pronto para começar?</h3>
        @if (session('contact_success'))
          <div class="alert alert-success mb-3">
            {{ session('contact_success') }}
          </div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger mb-3">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif
        <form method="POST" action="{{ route('contact.submit') }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-6">
              <label for="nome" class="form-label">Nome</label>
              <input
                type="text"
                class="form-control"
                id="nome"
                name="name"
                placeholder="O seu nome"
                value="{{ old('name') }}"
                required
              />
            </div>
            <div class="col-md-6">
              <label for="telefone" class="form-label">Telefone</label>
              <input
                type="text"
                class="form-control"
                id="telefone"
                name="phone"
                placeholder="O seu telefone"
                value="{{ old('phone') }}"
                required
              />
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email</label>
              <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                placeholder="O seu email"
                value="{{ old('email') }}"
                required
              />
            </div>
            <div class="col-12">
              <label for="mensagem" class="form-label">Mensagem</label>
              <textarea
                class="form-control"
                id="mensagem"
                name="message"
                rows="3"
                placeholder="Escreva a sua mensagem aqui"
                required
              >{{ old('message') }}</textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-submit">Enviar</button>
            </div>
          </div>
        </form>
      </div>
    </section>
