(function (blocks, element, components, blockEditor, serverSideRender) {
  var el = element.createElement;
  var SelectControl = components.SelectControl;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var ServerSideRender = serverSideRender
    ? serverSideRender.default || serverSideRender
    : wp.serverSideRender;

  blocks.registerBlockType("rafflecore/raffle", {
    title: "RaffleCore Rifa",
    icon: "tickets-alt",
    category: "widgets",
    description: rcBlock.i18n.description,
    attributes: {
      raffleId: { type: "number", default: 0 },
    },

    edit: function (props) {
      var raffleId = props.attributes.raffleId;

      return el(
        element.Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: rcBlock.i18n.panelTitle, initialOpen: true },
            el(SelectControl, {
              label: "Rifa",
              value: raffleId,
              options: rcBlock.raffles || [],
              onChange: function (val) {
                props.setAttributes({ raffleId: parseInt(val, 10) });
              },
            }),
          ),
        ),
        raffleId
          ? el(ServerSideRender, {
              block: "rafflecore/raffle",
              attributes: props.attributes,
            })
          : el(
              "div",
              {
                style: {
                  padding: "40px",
                  textAlign: "center",
                  background: "#f0f0f1",
                  borderRadius: "8px",
                  color: "#666",
                },
              },
              el("span", { style: { fontSize: "32px" } }, "🎟️"),
              el("p", null, rcBlock.i18n.selectHint),
            ),
      );
    },

    save: function () {
      return null; // Server-side rendered
    },
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.blockEditor,
  window.wp.serverSideRender,
);
