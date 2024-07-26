/**
 * Novo SGA - Monitor
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    new Vue({
        el: '#monitor',
        data: {
            search: '',
            searchResult: [],
            servicos: [],
            atendimento: null,
            novoServico: '',
            novaPrioridade: '',
            unidade: (unidade || {}),
            viewModal: null,
            transfereModal: null,
            buscaModal: null,
        },
        methods: {
            update: function () {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.monitor/ajax_update'),
                    data: {
                        ids: ids.join(',')
                    },
                    success: function (response) {
                        self.servicos = response.data;
                    }
                });
            },
            
            /**
             * Busca informacoes do atendimento pelo id.
             */
            view(atendimento) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.monitor/info_senha/') + atendimento.id,
                    success: function (response) {
                        self.atendimento = response.data;
                        self.viewModal.show();
                    }
                });
            },

            consulta() {
                this.buscaModal.show();
                this.consultar();
            },

            consultar: function () {
                var self = this;
                
                App.ajax({
                    url: App.url('/novosga.monitor/buscar'),
                    data: {
                        numero: self.search
                    },
                    success: function (response) {
                        self.searchResult = response.data;
                    }
                });
            },

            transfere(atendimento) {
                this.atendimento = atendimento;
                this.transfereModal.show();
            },

            transferir: function (atendimento, novoServico, novaPrioridade) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertTransferir,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    
                    App.ajax({
                        url: App.url('/novosga.monitor/transferir/') + atendimento.id,
                        type: 'post',
                        data: {
                            servico: parseInt(novoServico),
                            prioridade: parseInt(novaPrioridade)
                        },
                        success() {
                            App.Modal.closeAll();
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },

            reativar: function(atendimento) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertReativar,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                    //dangerMode: true,
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    
                    App.ajax({
                        url: App.url('/novosga.monitor/reativar/') + atendimento.id,
                        type: 'post',
                        success: function () {
                            App.Modal.closeAll();
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },

            cancelar: function(atendimento) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertCancelar,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                    //dangerMode: true,
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
            
                    App.ajax({
                        url: App.url('/novosga.monitor/cancelar/') + atendimento.id,
                        type: 'post',
                        success: function () {
                            App.Modal.closeAll();
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },
            
            totalPorSituacao: function (fila, prioridade) {
                var filtered = fila.filter(function (atendimento) {
                    if (prioridade) {
                        return atendimento.prioridade.peso > 0;
                    }
                    return atendimento.prioridade.peso === 0;
                });
                return filtered.length;
            },

            getItemFilaStyle(atendimento) {
                let styles = ['color: black']
                if (atendimento.prioridade.cor) {
                    styles.push(`color: ${atendimento.prioridade.cor}`)
                }
                return styles.join(';')
            }
        },
        mounted() {
            this.viewModal = new bootstrap.Modal(this.$refs.viewModal);
            this.transfereModal = new bootstrap.Modal(this.$refs.transfereModal);
            this.buscaModal = new bootstrap.Modal(this.$refs.buscaModal);

            App.SSE.connect([
                `/unidades/${this.unidade.id}/fila`
            ]);

            App.SSE.onmessage = (e, data) => {
                this.update();
            };

            // ajax polling fallback
            App.SSE.ondisconnect = () => {
                this.update();
            };
            
            this.update();
        }
    });
})();
